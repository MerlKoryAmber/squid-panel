<?php
/**
 * Round-trip prod-like conf through builder without touching live spm.db / squid.conf.
 * Expect: never_direct src before proxy_auth; key policy blocks still present; squid -k parse OK.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/Services/AclListFile.php';
require_once __DIR__ . '/../app/Services/PanelNet.php';
require_once __DIR__ . '/../app/Services/PanelTls.php';
require_once __DIR__ . '/../app/Services/SquidCachePolicy.php';
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

$fixture = __DIR__ . '/fixtures/prod_cascade_linux_mb.conf';
expect(is_readable($fixture), 'fixture readable');

$raw = file($fixture, FILE_IGNORE_NEW_LINES);
expect($raw !== false, 'fixture read');

$aclsByName = [];
$httpAccess = [];
$peers = [];
$peerAccess = [];
$routing = [];
$auth = [];
$globals = [
    'http_port' => '3128',
    'coredump_dir' => '/var/spool/squid',
    'request_header_access' => 'X-Forwarded-For deny all',
    'extra_conf' => "cache deny all\ncache_mem 0",
    'disable_cache' => 1,
];

foreach ($raw as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') {
        continue;
    }
    $t = preg_split('/\s+/', $line);
    $cmd = $t[0] ?? '';
    if ($cmd === 'acl' && count($t) >= 3) {
        $name = $t[1];
        $type = $t[2];
        $rest = array_slice($t, 3);
        if (!isset($aclsByName[$name])) {
            $storage = 'inline';
            $entries = [];
            if ($type === 'proxy_auth') {
                $storage = 'file';
                if (in_array('-i', $rest, true) || preg_match('#/acl\.d/#', implode(' ', $rest))) {
                    $storage = 'file';
                }
                if (implode(' ', $rest) === 'REQUIRED' || (count($rest) === 1 && $rest[0] === 'REQUIRED')) {
                    $storage = 'inline';
                    $entries = ['REQUIRED'];
                }
            }
            $aclsByName[$name] = [
                'name' => $name,
                'type' => $type,
                'storage' => $storage,
                'entries' => json_encode($entries),
            ];
        }
        if (($aclsByName[$name]['storage'] ?? '') === 'inline' && $type !== 'proxy_auth') {
            $vals = json_decode($aclsByName[$name]['entries'], true) ?: [];
            $vals[] = implode(' ', array_slice($t, 3));
            $aclsByName[$name]['entries'] = json_encode($vals);
        }
        continue;
    }
    if ($cmd === 'http_access' && count($t) >= 2) {
        $action = $t[1];
        $acls = array_slice($t, 2);
        if ($action === 'deny' && $acls === ['all']) {
            continue;
        }
        $httpAccess[] = ['action' => $action, 'acls' => json_encode($acls), 'enabled' => 1];
        continue;
    }
    if ($cmd === 'cache_peer' && count($t) >= 5) {
        $opts = [];
        $name = '';
        foreach (array_slice($t, 5) as $o) {
            if (strpos($o, 'name=') === 0) {
                $name = substr($o, 5);
            } else {
                $opts[] = $o;
            }
        }
        $peers[] = [
            'hostname' => $t[1],
            'peer_type' => $t[2],
            'http_port' => (int)$t[3],
            'icp_port' => (int)$t[4],
            'name' => $name !== '' ? $name : $t[1],
            'status' => 'active',
            'options' => implode(' ', $opts),
        ];
        continue;
    }
    if ($cmd === 'cache_peer_access' && count($t) >= 4) {
        $peerAccess[] = [
            'peer_name' => $t[1],
            'hostname' => $t[1],
            'action' => $t[2],
            'acl_entries' => implode(' ', array_slice($t, 3)),
            'acl_name' => $t[3],
        ];
        continue;
    }
    if (($cmd === 'never_direct' || $cmd === 'always_direct') && count($t) >= 3) {
        $routing[] = [
            'directive' => $cmd,
            'action' => $t[1],
            'acl_name' => implode(' ', array_slice($t, 2)),
            'negated' => 0,
        ];
        continue;
    }
    if ($cmd === 'auth_param' && ($t[1] ?? '') === 'negotiate' && ($t[2] ?? '') === 'program') {
        $auth = [[
            'scheme' => 'negotiate',
            'program' => implode(' ', array_slice($t, 3)),
            'children' => 60,
            'children_extra' => 'startup=40 idle=20',
            'keep_alive' => 'on',
            'realm' => '',
            'ldap_servers' => '',
        ]];
    }
}

$b = (new SquidConfigBuilder())->loadFromArray([
    'acls' => array_values($aclsByName),
    'http_access' => $httpAccess,
    'peers' => $peers,
    'peer_access' => $peerAccess,
    'routing' => $routing,
    'auth' => $auth,
    'ext_acl' => [],
    'globals' => $globals,
]);

$out = $b->generate();
expect($out !== '', 'generate non-empty');

$posLinux = strpos($out, 'never_direct allow LinuxToMBProxy');
$posAd = strpos($out, 'never_direct allow ad_proxy_mb_policy');
$posAlways = strpos($out, 'always_direct allow Internal_Network');
$posHci = strpos($out, 'never_direct allow HCIUsrPC');
expect($posLinux !== false, 'emits never_direct LinuxToMBProxy');
expect($posAd !== false, 'emits never_direct ad_proxy_mb_policy');
expect($posLinux < $posAd, 'LinuxToMBProxy before ad_proxy_mb_policy');
expect($posHci !== false && $posHci < $posAd, 'HCIUsrPC before ad_proxy_mb_policy');
expect($posAlways !== false && $posAlways < $posAd, 'always_direct before auth never_direct');

expect(strpos($out, 'cache deny all') !== false, 'cache deny kept');
expect(strpos($out, 'auth_param negotiate program') !== false, 'auth_param kept');
expect(strpos($out, 'name=ksmg') !== false, 'peer ksmg');
expect(strpos($out, 'name=cprx-01') !== false, 'peer cprx-01');
expect(strpos($out, 'name=MBhproxy') !== false, 'peer MBhproxy');
expect(strpos($out, 'name=MBhproxy-IP') !== false, 'peer MBhproxy-IP');
expect(strpos($out, 'cache_peer_access MBhproxy-IP allow LinuxToMBProxy') !== false, 'peer_access Linux→IP');
expect(strpos($out, 'cache_peer_access MBhproxy allow ad_proxy_mb_policy') !== false, 'peer_access AD→MB');
expect(strpos($out, 'http_access allow LinuxToMBProxy') !== false, 'http_access Linux');
expect(strpos($out, 'http_access deny !authenticated_user') !== false, 'http_access auth gate');
expect(substr_count($out, 'http_access deny all') === 1, 'one deny all');
expect(strpos($out, 'http_port 3128') !== false, 'http_port');
expect(strpos($out, 'ext_kerberos_ldap_group_acl') === false, 'no ldap group helper');

$tmp = '/tmp/spm-nd-order-test.conf';
expect(file_put_contents($tmp, $out) !== false, 'write tmp conf');

$parseOut = [];
$parseCode = 0;
exec('/usr/sbin/squid -f ' . escapeshellarg($tmp) . ' -k parse 2>&1', $parseOut, $parseCode);
$parseText = implode("\n", $parseOut);
expect($parseCode === 0, 'squid -k parse exit 0');
expect(stripos($parseText, 'FATAL') === false, 'squid -k parse no FATAL');
echo "parse_out: " . substr($parseText, 0, 200) . (strlen($parseText) > 200 ? '…' : '') . "\n";

@unlink($tmp);

if ($fail > 0) {
    exit(1);
}
echo "PASS prod_cascade never_direct order + parse\n";
