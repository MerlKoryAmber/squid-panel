<?php
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
    'acls' => [
        ['name' => 'office', 'type' => 'src', 'storage' => 'inline', 'entries' => json_encode(['10.0.0.0/8'])],
        ['name' => 'banks', 'type' => 'dstdomain', 'storage' => 'file', 'entries' => '[]'],
        ['name' => 'DIT_AD', 'type' => 'external', 'storage' => 'inline', 'entries' => json_encode(['www_DIT_Allow'])],
    ],
    'http_access' => [
        ['action' => 'allow', 'acls' => json_encode(['office', 'banks']), 'enabled' => 1],
        ['action' => 'deny', 'acls' => json_encode(['office']), 'enabled' => 0],
    ],
    'peers' => [
        ['hostname' => 'up.example', 'peer_type' => 'parent', 'http_port' => 8080, 'icp_port' => 0, 'name' => 'up1', 'status' => 'active', 'options' => ''],
    ],
    'peer_access' => [
        ['peer_name' => 'up1', 'hostname' => 'up.example', 'acl_entries' => 'office', 'acl_name' => 'office', 'action' => 'allow'],
    ],
    'routing' => [
        ['directive' => 'never_direct', 'action' => 'allow', 'acl_name' => 'office', 'negated' => 0],
    ],
    'auth' => [
        [
            'scheme' => 'negotiate',
            'program' => '/usr/lib64/squid/negotiate_kerberos_auth -k /etc/krb5.keytab -s HTTP/hprx-01.hci.interros.ru@HCI.INTERROS.RU',
            'children' => 20,
            'children_extra' => 'startup=0 idle=10',
            'keep_alive' => 'on',
            'realm' => 'HCI.INTERROS.RU',
            'ldap_servers' => "hdc-01.hci.interros.ru\nhdc-02.hci.interros.ru",
        ],
    ],
    'ext_acl' => [
        [
            'name' => 'www_DIT_Allow',
            'format' => '%LOGIN',
            'ttl' => 300,
            'negative_ttl' => 60,
            'children' => 10,
            'program' => '/usr/lib64/squid/ext_kerberos_ldap_group_acl',
            'options' => '-a -g WWW_DIT_Allow -D HCI.INTERROS.RU',
        ],
    ],
    'globals' => [
        'http_port' => '3128',
        'coredump_dir' => '/var/spool/squid',
        'request_header_access' => 'X-Forwarded-For deny all',
        'extra_conf' => "cache deny all\ncache_mem 0",
    ],
]);

$acl = $b->fragmentAcl();
expect(strpos($acl, 'acl office src 10.0.0.0/8') !== false, 'inline acl');
expect(strpos($acl, '/etc/squid/acl.d/banks.txt') !== false, 'file acl quoted path');

$http = $b->fragmentHttpAccess();
expect(strpos($http, 'http_access allow office banks') !== false, 'enabled rule');
expect(strpos($http, 'http_access deny office') === false, 'disabled rule skipped');
expect(substr_count($http, 'http_access deny all') === 1, 'one deny all');

$peers = $b->fragmentPeers();
expect(strpos($peers, 'cache_peer up.example parent 8080 0') !== false, 'cache_peer');
expect(strpos($peers, 'name=up1') !== false, 'peer name=');
expect(strpos($peers, 'cache_peer_access up1 allow office') !== false, 'peer access');
expect(strpos($peers, 'never_direct allow office') !== false, 'never_direct');

$out = $b->generate();
$auth = strpos($out, 'auth_param negotiate program');
$ext = strpos($out, 'external_acl_type www_DIT_Allow');
$dit = strpos($out, 'acl DIT_AD external www_DIT_Allow');
$port = strpos($out, 'http_port 3128');
expect($auth !== false, 'auth_param emitted');
expect(strpos($out, 'auth_param negotiate realm') === false, 'negotiate realm not emitted as squid realm');
expect($ext !== false && $auth !== false && $ext > $auth, 'external_acl after auth_param');
expect($dit !== false && $ext !== false && $dit > $ext, 'acl DIT_AD after helper');
expect(strpos($out, '-S hdc-01.hci.interros.ru@HCI.INTERROS.RU:hdc-02.hci.interros.ru@HCI.INTERROS.RU') !== false, 'ldap -S pinned');
expect(strpos($out, 'cache_mem 0') !== false, 'cache_mem extra kept');
expect(strpos($out, 'coredump_dir /var/spool/squid') !== false, 'coredump_dir');
expect(strpos($out, 'request_header_access X-Forwarded-For deny all') !== false, 'request_header_access');
expect($port !== false, 'http_port');
expect(strpos($out, 'include /etc/squid/spm-acl.conf') === false, 'no policy includes');

try {
    $b->save();
    expect(false, 'save must throw');
} catch (Exception $e) {
    expect(strpos($e->getMessage(), 'must not write') !== false, 'save refuses live conf from PHP');
}

if ($fail > 0) {
    exit(1);
}
echo "PASS\n";
