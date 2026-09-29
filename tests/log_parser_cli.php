<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/Services/LogParser.php';

function expect($cond, $msg) {
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    echo "OK: $msg\n";
}

$tmp = tempnam(sys_get_temp_dir(), 'spm-log-');
$now = time();
$lines = [
    // Classic short form
    ($now - 100) . " 10 1.1.1.1 TCP_MISS/200 100 GET http://old.example/ - DIRECT/- -",
    // Real Squid native: HIER_* — Direct filter must match HIER_DIRECT, not HIER_NONE
    ($now - 50) . " 10 4.4.4.4 TCP_DENIED/407 3452 GET http://ya.ru/ - HIER_NONE/- -",
    ($now - 40) . " 10 5.5.5.5 TCP_MISS/503 3420 GET http://ya.ru/ - HIER_DIRECT/5.255.255.242 -",
    ($now - 30) . " 10 2.2.2.2 TCP_MISS/200 200 GET http://mid.example/ user1 PARENT_HIT/peer-a -",
    ($now - 10) . " 10 3.3.3.3 TCP_MISS/200 300 GET http://new.example/ user2 FIRST_UP_PARENT/peer-b -",
];
file_put_contents($tmp, implode("\n", $lines) . "\n");

$parsedDirect = LogParser::parseLine($lines[2]);
expect(($parsedDirect['hierarchy'] ?? '') === 'DIRECT', 'parse strips HIER_ from HIER_DIRECT');
$parsedNone = LogParser::parseLine($lines[1]);
expect(($parsedNone['hierarchy'] ?? '') === 'NONE', 'parse strips HIER_ from HIER_NONE');

$all = LogParser::filter($tmp, [], 10);
expect(count($all) === 5, 'filter returns five rows');
expect($all[0]['url'] === 'http://new.example/', 'newest row first');

$peerA = LogParser::filter($tmp, ['peer' => 'peer-a', 'peer_hostname' => 'peer-a.example'], 10);
expect(count($peerA) === 1, 'peer filter matches peer name');
expect($peerA[0]['client_ip'] === '2.2.2.2', 'peer filter row is correct');

$direct = LogParser::filter($tmp, ['peer' => 'DIRECT'], 10);
expect(count($direct) === 2, 'direct filter matches DIRECT and HIER_DIRECT only');
$directIps = array_column($direct, 'client_ip');
sort($directIps);
expect($directIps === ['1.1.1.1', '5.5.5.5'], 'direct filter excludes HIER_NONE');
expect(!in_array('4.4.4.4', array_column($direct, 'client_ip'), true), 'direct filter excludes HIER_NONE client');

$big = tempnam(sys_get_temp_dir(), 'spm-log-big-');
$chunk = str_repeat(($now - 5) . " 10 9.9.9.9 TCP_MISS/200 100 GET http://bulk.example/ - DIRECT/- -\n", 5000);
file_put_contents($big, $chunk . ($now - 1) . " 10 8.8.8.8 TCP_MISS/200 100 GET http://tail.example/ - DIRECT/- -\n");
$tailHit = LogParser::filter($big, ['ip' => '8.8.8.8'], 5, 65536);
expect(count($tailHit) === 1, 'tail window finds recent match in large file');
expect($tailHit[0]['url'] === 'http://tail.example/', 'tail window match is newest row');

$stats = LogParser::getStats($tmp, 24);
expect(($stats['total_requests'] ?? 0) === 5, 'getStats counts rows in window');
expect(empty($stats['error']), 'getStats no error');

unlink($big);
unlink($tmp);
echo "All log parser checks passed.\n";
