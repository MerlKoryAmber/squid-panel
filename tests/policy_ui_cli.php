<?php
require_once __DIR__ . '/../app/Services/PolicyAclKind.php';
require_once __DIR__ . '/../app/Services/CascadeRouteCompiler.php';

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

expect(PolicyAclKind::kind('office', 'src') === 'from', 'src is from');
expect(PolicyAclKind::kind('ad_hr', 'external') === 'from', 'ad_* is from');
expect(PolicyAclKind::kind('banks', 'dstdomain', 'file') === 'to', 'dstdomain file is to');
expect(PolicyAclKind::label(['name' => 'localnet', 'description' => 'Imported from squid.conf']) === 'localnet', 'generic import desc uses name');
expect(PolicyAclKind::label(['name' => 'hr', 'type' => 'src'], true) === 'hr (src)', 'label with type');
$prev = PolicyAclKind::labelWithPreview([
    'name' => 'office',
    'type' => 'src',
    'storage' => 'inline',
    'entries' => json_encode(['10.0.0.1', '10.0.0.2', '10.0.0.3', '10.0.0.4']),
]);
expect($prev === 'office — 10.0.0.1, 10.0.0.2, 10.0.0.3 (+1)', 'preview +N');
expect(PolicyAclKind::selectOption([
    'name' => 'office',
    'type' => 'src',
    'storage' => 'inline',
    'entries' => json_encode(['10.0.0.1', '10.0.0.2']),
]) === 'office (src) — 10.0.0.1, 10.0.0.2', 'select option shows values');
$ad = PolicyAclKind::labelWithPreview(['name' => 'ad_hr', 'type' => 'external', 'entries' => '[]']);
expect(strpos($ad, 'live LDAP') !== false, 'AD preview is LDAP note');

$catalog = [
    'office' => ['name' => 'office', 'type' => 'src', 'storage' => 'inline'],
    'banks' => ['name' => 'banks', 'type' => 'dstdomain', 'storage' => 'file'],
    'SSL_ports' => ['name' => 'SSL_ports', 'type' => 'port', 'storage' => 'inline'],
];
$a = PolicyAclKind::analyze(['office', 'banks'], $catalog);
expect($a['simple'] === true, 'from+to simple');
$b = PolicyAclKind::analyze(['office', 'SSL_ports'], $catalog);
expect($b['simple'] === false, 'port makes complex');
$cols = PolicyAclKind::columnLabels($b, $catalog);
expect($cols['from'] === ['office'], 'complex initiator still office');
expect($cols['to'] === ['SSL_ports'], 'port listed as traffic filter, not Complex');
$c = PolicyAclKind::analyze(['!office', 'banks'], $catalog);
$cCols = PolicyAclKind::columnLabels($c, $catalog);
expect($cCols['from'][0] === 'except office', 'negation is except + name');
expect($c['simple'] === false, 'negation complex');

$peers = [
    ['id' => 1, 'hostname' => 'p1.example', 'status' => 'active'],
    ['id' => 2, 'hostname' => 'p2.example', 'status' => 'active'],
];
$plan = CascadeRouteCompiler::plan([
    ['from' => ['office'], 'to' => ['banks'], 'channel' => 'peer', 'peer_id' => 1],
    ['from' => ['office'], 'to' => [], 'channel' => 'direct', 'peer_id' => null],
], $peers);
$allows = 0;
$denies = 0;
foreach ($plan['access'] as $row) {
    if ($row['action'] === 'allow' && (int)$row['peer_id'] === 1) {
        $allows++;
    }
    if ($row['action'] === 'deny' && (int)$row['peer_id'] === 2) {
        $denies++;
    }
}
expect($allows === 1, 'allow on chosen peer');
expect($denies === 1, 'deny on other peer');
$dirs = array_column($plan['routing'], 'directive');
expect(in_array('never_direct', $dirs, true), 'never_direct for peer route');
expect(in_array('always_direct', $dirs, true), 'always_direct for direct route');

if ($fail > 0) {
    exit(1);
}
echo "PASS\n";
