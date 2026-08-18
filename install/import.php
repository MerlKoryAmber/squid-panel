<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/squid.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Services/SquidConfigParser.php';

Database::init();

if (!file_exists(SQUID_CONF)) {
    echo "No squid.conf found at " . SQUID_CONF . "
";
    exit(1);
}

$result = SquidConfigParser::parseAndImport(SQUID_CONF);

if ($result['success']) {
    echo "Import completed successfully.
";
    echo "Stats:
";
    foreach ($result['stats'] as $key => $count) {
        echo "  {$key}: {$count}
";
    }

    // Debug: show imported peers and access rules
    $peers = Database::fetchAll("SELECT id, name, hostname FROM cache_peers");
    echo "
Imported peers (" . count($peers) . "):
";
    foreach ($peers as $p) {
        echo "  - {$p['name']} {$p['hostname']} (id={$p['id']})
";
    }

    $access = Database::fetchAll("SELECT peer_id, hostname, acl_entries, action FROM cache_peer_access_rules");
    echo "
Imported peer access rules (" . count($access) . "):
";
    foreach ($access as $a) {
        echo "  - peer_id={$a['peer_id']} {$a['action']} {$a['acl_entries']}
";
    }

    $routing = Database::fetchAll("SELECT directive, action, acl_name, negated FROM routing_rules");
    echo "
Imported routing rules (" . count($routing) . "):
";
    foreach ($routing as $r) {
        $neg = $r['negated'] ? '!' : '';
        echo "  - {$r['directive']} {$r['action']} {$neg}{$r['acl_name']}
";
    }
} else {
    echo "Import failed: " . $result['error'] . "
";
}
