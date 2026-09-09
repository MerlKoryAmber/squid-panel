<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Services/PanelTimezone.php';

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

expect(PanelTimezone::normalize('UTC') === 'UTC', 'normalize UTC');
expect(PanelTimezone::normalize('bogus') === 'Europe/Moscow', 'normalize default');
expect(PanelTimezone::normalize('') === 'Europe/Moscow', 'normalize empty');

date_default_timezone_set('UTC');
$ts = strtotime('2026-09-09 12:00:00 UTC');
date_default_timezone_set('Europe/Moscow');
$msk = date('Y-m-d H:i:s', $ts);
expect($msk === '2026-09-09 15:00:00', 'MSK +3 from UTC epoch');

exit($fail > 0 ? 1 : 0);
