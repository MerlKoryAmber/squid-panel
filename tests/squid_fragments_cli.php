<?php
require_once __DIR__ . '/../app/Services/AclListFile.php';
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

try {
    $b->save();
    expect(false, 'save must throw');
} catch (Exception $e) {
    expect(strpos($e->getMessage(), 'Refusing') !== false, 'save refuses live conf');
}

if ($fail > 0) {
    exit(1);
}
echo "PASS\n";
