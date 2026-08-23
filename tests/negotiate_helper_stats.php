<?php
require_once __DIR__ . '/../app/Services/NegotiateHelperStats.php';

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

$sample = <<<LOG
2026/08/22 08:00:00 kid1| ERROR: something else
2026/08/23 11:26:23 kid1| WARNING: All 20/20 negotiateauthenticator processes are busy.
2026/08/23 11:26:23 kid1| WARNING: 30 pending requests queued
2026/08/23 11:26:23 kid1| WARNING: Consider increasing the number of negotiateauthenticator processes in your config file.
2026/08/23 12:00:00 kid1| FATAL: Too many queued negotiateauthenticator requests
LOG;

$since = strtotime('2026-08-23 00:00:00');
$r = NegotiateHelperStats::parseChunk($sample, $since);
expect($r['ok'] === false, 'not ok when busy');
expect($r['busy_count'] === 1, 'one busy');
expect($r['max_queued'] === 30, 'queued 30');
expect($r['fatal_count'] === 1, 'one fatal');
expect($r['busy_ratio'] === '20/20', 'ratio');

$quiet = "2026/08/23 10:00:00 kid1| Starting Squid\n";
$q = NegotiateHelperStats::parseChunk($quiet, $since);
expect($q['ok'] === true, 'ok when quiet');
expect($q['busy_count'] === 0, 'zero busy');

if ($fail > 0) {
    exit(1);
}
echo "ALL_OK\n";
