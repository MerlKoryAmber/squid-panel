<?php
/**
 * SPM Installation — Import existing squid.conf
 */

define('SPM_ROOT', '/opt/spm');
define('SPM_STORAGE', '/opt/spm/storage');
define('SPM_CONFIG', '/opt/spm/config');
define('DB_PATH', '/opt/spm/database/spm.db');
define('SQUID_CONF', '/etc/squid/squid.conf');
define('SQUID_CONF_DIR', '/etc/squid');
define('SQUID_LOG_DIR', '/var/log/squid');
define('SQUID_ACCESS_LOG', '/var/log/squid/access.log');
define('SQUID_CACHE_LOG', '/var/log/squid/cache.log');
define('SQUID_BINARY', '/usr/sbin/squid');
define('AGENT_SOCKET', '/run/spmd.sock');
define('AGENT_ENABLED', true);
define('SESSION_LIFETIME', 3600);
define('CSRF_TOKEN_NAME', 'spm_csrf_token');
define('AUDIT_RETENTION_DAYS', 30);
define('DEFAULT_LANG', 'ru');

require_once SPM_ROOT . '/app/Core/Database.php';
require_once SPM_ROOT . '/app/Services/SquidConfigParser.php';

Database::init();

echo "Importing configuration from " . SQUID_CONF . "...
";
$result = SquidConfigParser::parseAndImport(SQUID_CONF);

if ($result['success']) {
    echo "Import successful!
";
    foreach ($result['stats'] as $key => $count) {
        echo "  - $key: $count
";
    }
} else {
    echo "WARNING: Import failed: " . $result['error'] . "
";
}
