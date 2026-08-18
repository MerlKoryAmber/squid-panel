<?php
/**
 * SPM Application Configuration
 */

define('SPM_VERSION', '1.0.0');
define('SPM_ROOT', dirname(__DIR__));
define('SPM_STORAGE', SPM_ROOT . '/storage');
define('SPM_VIEWS', SPM_ROOT . '/views');
define('SPM_CONFIG', SPM_ROOT . '/config');

// Database
 define('DB_PATH', SPM_ROOT . '/database/spm.db');

// Squid paths
 define('SQUID_CONF', '/etc/squid/squid.conf');
 define('SQUID_CONF_DIR', '/etc/squid');
 define('SQUID_LOG_DIR', '/var/log/squid');
 define('SQUID_ACCESS_LOG', '/var/log/squid/access.log');
 define('SQUID_CACHE_LOG', '/var/log/squid/cache.log');
 define('SQUID_BINARY', '/usr/sbin/squid');
 // Candidate conf for squid -f ... -k parse (must match sudoers/spmd on CentOS)
 define('SQUID_PARSE_FILE', '/opt/spm/storage/tmp/squid.conf.parse');

// Agent
 define('AGENT_SOCKET', '/run/spmd.sock');
 define('AGENT_ENABLED', true);

// Security
 define('SESSION_LIFETIME', 3600);
 define('CSRF_TOKEN_NAME', 'spm_csrf_token');
 define('AUDIT_RETENTION_DAYS', 30);

// Localization
 define('DEFAULT_LANG', 'ru');

// Ensure storage exists
$dirs = [SPM_STORAGE, SPM_STORAGE . '/logs', SPM_STORAGE . '/tmp'];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
}

/**
 * Get absolute path inside storage directory
 */
function storage_path($subpath = '') {
    return $subpath ? SPM_STORAGE . '/' . $subpath : SPM_STORAGE;
}
