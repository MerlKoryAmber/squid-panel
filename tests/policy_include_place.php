<?php
/**
 * Placement of policy includes: after auth/external_acl_type, not at first acl.
 * Keep in sync with agent/spmd.py _ensure_policy_includes.
 */
$fail = 0;
function expect($ok, $msg) {
    global $fail;
    if (!$ok) {
        fwrite(STDERR, "FAIL $msg\n");
        $fail++;
    } else {
        echo "ok $msg\n";
    }
}

function spm_ensure_policy_includes($text) {
    $mark = '# SPM managed ACL / access / cascade';
    $incs = [
        'include /etc/squid/spm-acl.conf',
        'include /etc/squid/spm-peers.conf',
        'include /etc/squid/spm-http_access.conf',
    ];
    $drop = array_flip(array_merge([$mark], $incs));
    $outLines = [];
    foreach (preg_split("/\r\n|\n|\r/", $text) as $line) {
        if (isset($drop[trim($line)])) {
            continue;
        }
        $outLines[] = $line;
    }
    $insertAt = 0;
    foreach ($outLines as $i => $line) {
        $s = ltrim($line);
        if ($s === '' || (isset($s[0]) && $s[0] === '#')) {
            continue;
        }
        if (strpos($s, 'auth_param ') === 0
            || strpos($s, 'external_acl_type ') === 0
            || strpos($s, 'include /etc/squid/spm-listen.conf') === 0) {
            $insertAt = $i + 1;
        }
    }
    array_splice($outLines, $insertAt, 0, array_merge([$mark], $incs));
    return implode("\n", $outLines);
}

$src = implode("\n", [
    'cache_mem 0',
    'auth_param negotiate program /usr/lib64/squid/negotiate_kerberos_auth -k /etc/krb5.keytab',
    'acl CYPInet dstdomain .zoom.us',
    'external_acl_type www_DIT_Allow ttl=3600 %LOGIN /usr/lib64/squid/ext_kerberos_ldap_group_acl',
    'acl DIT_AD external www_DIT_Allow',
    'http_access allow DIT_AD',
]);
$out = spm_ensure_policy_includes($src);
$aclInc = strpos($out, 'include /etc/squid/spm-acl.conf');
$ext = strpos($out, 'external_acl_type www_DIT_Allow');
$auth = strpos($out, 'auth_param negotiate');
expect($aclInc !== false && $ext !== false && $aclInc > $ext, 'spm-acl include after external_acl_type');
expect($auth !== false && $aclInc > $auth, 'include after auth_param');

if ($fail > 0) {
    exit(1);
}
echo "PASS\n";
