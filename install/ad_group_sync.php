<?php
/**
 * CLI: sync AD group members (ADR 0010). Invoked by systemd timer.
 * Usage: php /opt/spm/install/ad_group_sync.php
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/config/app.php';
require_once $root . '/app/Core/Database.php';
require_once $root . '/app/Core/Audit.php';
foreach (glob($root . '/app/Services/*.php') as $file) {
    require_once $file;
}

Database::init();

try {
    $result = AdGroupMemberSync::syncAll();
    echo $result['message'] . "\n";
    if (!empty($result['changed'])) {
        $apply = SquidLiveApply::run();
        if (empty($apply['success'])) {
            fwrite(STDERR, SquidLiveApply::errorText($apply) . "\n");
            exit(2);
        }
        echo "Config saved\n";
    }
    exit(!empty($result['errors']) ? 1 : 0);
} catch (Throwable $e) {
    AdGroupMemberSync::setMeta(false, $e->getMessage());
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
