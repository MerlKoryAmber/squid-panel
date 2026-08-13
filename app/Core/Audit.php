<?php
class Audit {
    public static function log($action, $details = '') {
        $user = Auth::user();
        $username = $user ? $user['username'] : 'guest';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        Database::query(
            "INSERT INTO audit_logs (user, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, datetime('now'))",
            [$username, $action, $details, $ip]
        );

        // Also write to file log
        $logLine = date('Y-m-d H:i:s') . " | {$username} | {$ip} | {$action} | {$details}" . PHP_EOL;
        file_put_contents(storage_path('logs', 'audit.log'), $logLine, FILE_APPEND | LOCK_EX);

        // Cleanup old records
        self::cleanup();
    }

    private static function cleanup() {
        static $lastCleanup = 0;
        if (time() - $lastCleanup < 3600) return;
        $lastCleanup = time();

        Database::query("DELETE FROM audit_logs WHERE created_at < datetime('now', '-" . AUDIT_RETENTION_DAYS . " days')");
    }

    public static function getRecent($limit = 50, $offset = 0) {
        return Database::fetchAll(
            "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    public static function getFiltered($filters = [], $limit = 50, $offset = 0) {
        $where = [];
        $params = [];

        if (!empty($filters['user'])) {
            $where[] = "user LIKE ?";
            $params[] = '%' . $filters['user'] . '%';
        }
        if (!empty($filters['action'])) {
            $where[] = "action = ?";
            $params[] = $filters['action'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $sql = "SELECT * FROM audit_logs";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return Database::fetchAll($sql, $params);
    }
}
