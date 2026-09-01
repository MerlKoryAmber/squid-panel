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
$lines = [
    "1000 10 1.1.1.1 TCP_MISS/200 100 GET http://old.example/ - DIRECT/- -",
    "2000 10 2.2.2.2 TCP_MISS/200 200 GET http://mid.example/ user1 PARENT_HIT/peer-a -",
    "3000 10 3.3.3.3 TCP_MISS/200 300 GET http://new.example/ user2 PARENT_HIT/peer-b -",
];
file_put_contents($tmp, implode("\n", $lines) . "\n");

$all = LogParser::filter($tmp, [], 10);
expect(count($all) === 3, 'filter returns three rows');
expect($all[0]['url'] === 'http://new.example/', 'newest row first');

$peerA = LogParser::filter($tmp, ['peer' => 'peer-a', 'peer_hostname' => 'peer-a.example'], 10);
expect(count($peerA) === 1, 'peer filter matches peer name');
expect($peerA[0]['client_ip'] === '2.2.2.2', 'peer filter row is correct');

$direct = LogParser::filter($tmp, ['peer' => 'DIRECT'], 10);
expect(count($direct) === 1, 'direct filter matches one row');
expect($direct[0]['client_ip'] === '1.1.1.1', 'direct filter row is correct');

unlink($tmp);
echo "All log parser checks passed.\n";
