<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/squid.php';
require_once __DIR__ . '/../app/Core/Database.php';

foreach (glob(__DIR__ . '/../app/Services/*.php') as $file) {
    require_once $file;
}

Database::init();

if (!file_exists(SQUID_CONF)) {
    echo "No squid.conf found at " . SQUID_CONF . "\n";
    exit(1);
}

$result = SquidConfigParser::parseAndImport(SQUID_CONF);

if (empty($result['success'])) {
    echo "Import failed: " . ($result['error'] ?? 'unknown error') . "\n";
    exit(1);
}

echo "Import completed successfully.\n";
echo "Stats:\n";
foreach ($result['stats'] as $key => $count) {
    echo "  {$key}: {$count}\n";
}

$acls = Database::fetchAll("SELECT name, type, storage FROM acls ORDER BY id");
echo "\nImported ACLs (" . count($acls) . "):\n";
foreach ($acls as $a) {
    echo "  - {$a['name']} {$a['type']} {$a['storage']}\n";
}

$http = Database::fetchAll("SELECT action, acls FROM http_access_rules ORDER BY sort_order, id");
echo "\nImported http_access (" . count($http) . "):\n";
foreach ($http as $h) {
    $list = implode(' ', json_decode($h['acls'], true) ?: []);
    echo "  - {$h['action']} {$list}\n";
}

$peers = Database::fetchAll("SELECT id, name, hostname FROM cache_peers");
echo "\nImported peers (" . count($peers) . "):\n";
foreach ($peers as $p) {
    echo "  - {$p['name']} {$p['hostname']} (id={$p['id']})\n";
}

$access = Database::fetchAll("SELECT peer_id, hostname, acl_entries, action FROM cache_peer_access_rules");
echo "\nImported peer access rules (" . count($access) . "):\n";
foreach ($access as $a) {
    echo "  - peer_id={$a['peer_id']} {$a['action']} {$a['acl_entries']}\n";
}

$routing = Database::fetchAll("SELECT directive, action, acl_name, negated FROM routing_rules");
echo "\nImported routing rules (" . count($routing) . "):\n";
foreach ($routing as $r) {
    $neg = $r['negated'] ? '!' : '';
    echo "  - {$r['directive']} {$r['action']} {$neg}{$r['acl_name']}\n";
}
