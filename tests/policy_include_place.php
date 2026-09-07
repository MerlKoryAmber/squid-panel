<?php
/**
 * Generated squid.conf order: auth_param then external_acl_type then acl.
 * ADR 0010: AD groups are proxy_auth files; use a non-LDAP helper for order check.
 */
require_once __DIR__ . '/../app/Services/AclListFile.php';
require_once __DIR__ . '/../app/Services/PanelNet.php';
require_once __DIR__ . '/../app/Services/SquidConfigBuilder.php';

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

$b = (new SquidConfigBuilder())->loadFromArray([
    'auth' => [[
        'scheme' => 'negotiate',
        'program' => '/usr/lib64/squid/negotiate_kerberos_auth -k /etc/krb5.keytab',
        'children' => 5,
        'children_extra' => '',
        'keep_alive' => 'on',
    ]],
    'ext_acl' => [[
        'name' => 'session_check',
        'format' => '%LOGIN',
        'ttl' => 3600,
        'negative_ttl' => 60,
        'children' => 10,
        'program' => '/usr/lib64/squid/ext_session_acl',
        'options' => '',
    ]],
    'acls' => [
        [
            'name' => 'sess',
            'type' => 'external',
            'storage' => 'inline',
            'entries' => json_encode(['session_check']),
        ],
        [
            'name' => 'ad_Demo',
            'type' => 'proxy_auth',
            'storage' => 'file',
            'entries' => '[]',
            'group_name' => 'Demo',
        ],
    ],
    'http_access' => [],
    'peers' => [],
    'globals' => ['http_port' => '3128', 'extra_conf' => 'cache_mem 0'],
]);
$out = $b->generate();
$auth = strpos($out, 'auth_param negotiate');
$ext = strpos($out, 'external_acl_type session_check');
$acl = strpos($out, 'acl sess external');
$ad = strpos($out, 'acl ad_Demo proxy_auth -i ');
expect($auth !== false && $ext !== false && $acl !== false, 'auth+ext+acl present');
expect($ext > $auth, 'external_acl after auth_param');
expect($acl > $ext, 'acl after external_acl_type');
expect($ad !== false, 'ad proxy_auth -i present');
expect(strpos($out, 'ext_kerberos_ldap_group_acl') === false, 'no kerberos ldap group helper');

if ($fail > 0) {
    exit(1);
}
echo "PASS\n";
