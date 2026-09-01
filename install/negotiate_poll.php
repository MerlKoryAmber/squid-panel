<?php
/**
 * Hourly negotiate helper stats from cache.log → spm.db.
 * Cron: 0 * * * * squidmgr php /opt/spm/install/negotiate_poll.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/Core/Database.php';

foreach (glob(__DIR__ . '/../app/Services/*.php') as $file) {
    require_once $file;
}

Database::init();

$result = NegotiateHelperStats::recordHourlySample();
if (!empty($result['skipped'])) {
    echo "skip: " . ($result['reason'] ?? '') . "\n";
    exit(0);
}

echo sprintf(
    "hour=%s busy=%d max_q=%d fatal=%d\n",
    $result['hour'] ?? '',
    (int)($result['busy_count'] ?? 0),
    (int)($result['max_queued'] ?? 0),
    (int)($result['fatal_count'] ?? 0)
);
