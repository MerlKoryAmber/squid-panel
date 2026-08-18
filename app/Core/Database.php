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
        self::addColumnIfMissing('auth_config', 'helper', "TEXT DEFAULT ''");
        self::addColumnIfMissing('routing_rules', 'sort_order', 'INTEGER DEFAULT 0');
        self::addColumnIfMissing('acls', 'storage', "TEXT NOT NULL DEFAULT 'inline'");
    }

    private static function addColumnIfMissing($table, $column, $ddl) {
        $allowed = [
            'cache_peers' => true,
            'cache_peer_access_rules' => true,
            'auth_config' => true,
            'routing_rules' => true,
            'acls' => true,
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
