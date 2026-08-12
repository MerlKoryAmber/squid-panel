<?php
class Database {
    private static $pdo = null;

    public static function init() {
        if (self::$pdo === null) {
            $exists = file_exists(DB_PATH);
            self::$pdo = new PDO('sqlite:' . DB_PATH);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            if (!$exists) {
                self::migrate();
            }
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
