<?php
class Database {
    private static $pdo = null;

    public static function init() {
        if (self::$pdo === null) {
            $exists = file_exists(DB_PATH);
            self::$pdo = new PDO('sqlite:' . DB_PATH);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$pdo->exec("PRAGMA journal_mode = WAL;");
            self::$pdo->exec("PRAGMA busy_timeout = 5000;");
            self::$pdo->exec("PRAGMA synchronous = NORMAL;");
            if (!$exists) {
                self::migrate();
            }
            self::ensureSchema();
        }
        return self::$pdo;
    }

    public static function get() {
        if (self::$pdo === null) {
            self::init();
        }
        return self::$pdo;
    }

    private static function migrate() {
        $sql = file_get_contents(SPM_ROOT . '/database/schema.sql');
        self::$pdo->exec($sql);

        // Create default admin user (password: admin)
        $hash = password_hash('admin', PASSWORD_BCRYPT);
        $stmt = self::$pdo->prepare("INSERT INTO users (username, password_hash, role, language, created_at) VALUES (?, ?, 'admin', 'ru', datetime('now'))");
        $stmt->execute(['admin', $hash]);
    }

    /**
     * Add columns that CREATE IF NOT EXISTS will not apply to an existing spm.db.
     */
    private static function ensureSchema() {
        self::addColumnIfMissing('cache_peers', 'name', "TEXT NOT NULL DEFAULT ''");
        self::addColumnIfMissing('cache_peers', 'status', "TEXT NOT NULL DEFAULT 'active'");
        self::addColumnIfMissing('cache_peer_access_rules', 'updated_at', 'TEXT');
        self::addColumnIfMissing('cache_peer_access_rules', 'acl_entries', "TEXT NOT NULL DEFAULT ''");
        self::addColumnIfMissing('auth_config', 'principal', "TEXT DEFAULT ''");
        self::addColumnIfMissing('auth_config', 'kdc', "TEXT DEFAULT ''");
        self::addColumnIfMissing('auth_config', 'admin_server', "TEXT DEFAULT ''");
        self::addColumnIfMissing('auth_config', 'ldap_servers', "TEXT DEFAULT ''");
        self::addColumnIfMissing('auth_config', 'helper', "TEXT DEFAULT ''");
        self::addColumnIfMissing('auth_config', 'children_extra', "TEXT DEFAULT ''");
        self::addColumnIfMissing('routing_rules', 'sort_order', 'INTEGER DEFAULT 0');
        self::addColumnIfMissing('acls', 'storage', "TEXT NOT NULL DEFAULT 'inline'");
        self::addColumnIfMissing('settings', 'panel_allow_ips', "TEXT NOT NULL DEFAULT ''");
        self::addColumnIfMissing('settings', 'simple_ui_enabled', "INTEGER NOT NULL DEFAULT 0");
        self::addColumnIfMissing('users', 'policy_ui', "TEXT NOT NULL DEFAULT 'expert'");
        self::addColumnIfMissing('http_access_rules', 'enabled', 'INTEGER NOT NULL DEFAULT 1');
        self::addColumnIfMissing('squid_globals', 'coredump_dir', "TEXT DEFAULT ''");
        self::addColumnIfMissing('squid_globals', 'extra_conf', "TEXT DEFAULT ''");
        self::addColumnIfMissing('squid_globals', 'request_header_access', "TEXT DEFAULT ''");
        self::$pdo->exec(
            "CREATE TABLE IF NOT EXISTS cascade_routes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                from_acls TEXT NOT NULL DEFAULT '[]',
                to_acls TEXT NOT NULL DEFAULT '[]',
                channel TEXT NOT NULL CHECK(channel IN ('peer', 'direct')),
                peer_id INTEGER,
                sort_order INTEGER DEFAULT 0,
                created_at TEXT,
                updated_at TEXT,
                FOREIGN KEY (peer_id) REFERENCES cache_peers(id) ON DELETE CASCADE
            )"
        );
        self::$pdo->exec(
            "CREATE TABLE IF NOT EXISTS external_acl_types (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE NOT NULL,
                format TEXT NOT NULL DEFAULT '%LOGIN',
                ttl INTEGER DEFAULT 3600,
                negative_ttl INTEGER DEFAULT 60,
                children INTEGER DEFAULT 10,
                program TEXT NOT NULL,
                options TEXT DEFAULT '',
                created_at TEXT,
                updated_at TEXT
            )"
        );
        self::$pdo->exec(
            "CREATE TABLE IF NOT EXISTS negotiate_helper_hourly (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                hour_start TEXT NOT NULL UNIQUE,
                busy_events INTEGER NOT NULL DEFAULT 0,
                max_queued INTEGER NOT NULL DEFAULT 0,
                fatal_events INTEGER NOT NULL DEFAULT 0,
                created_at TEXT
            )"
        );
        self::$pdo->exec(
            "CREATE INDEX IF NOT EXISTS idx_negotiate_helper_hourly_start ON negotiate_helper_hourly(hour_start)"
        );
    }

    private static function addColumnIfMissing($table, $column, $ddl) {
        $allowed = [
            'cache_peers' => true,
            'cache_peer_access_rules' => true,
            'auth_config' => true,
            'routing_rules' => true,
            'acls' => true,
            'settings' => true,
            'users' => true,
            'http_access_rules' => true,
            'squid_globals' => true,
        ];
        if (!isset($allowed[$table])) {
            return;
        }
        $stmt = self::$pdo->query('PRAGMA table_info(' . $table . ')');
        foreach ($stmt->fetchAll() as $col) {
            if (($col['name'] ?? '') === $column) {
                return;
            }
        }
        self::$pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$ddl}");
    }

    public static function query($sql, $params = []) {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch($sql, $params = []) {
        return self::query($sql, $params)->fetch();
    }

    public static function fetchAll($sql, $params = []) {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert($sql, $params = []) {
        self::query($sql, $params);
        return self::get()->lastInsertId();
    }
}
