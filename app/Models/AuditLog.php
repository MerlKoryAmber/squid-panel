<?php
class AuditLog {
    public static function all($limit = 100) {
        return Database::fetchAll("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT ?", [$limit]);
    }

    public static function count() {
        $result = Database::fetch("SELECT COUNT(*) as cnt FROM audit_logs");
        return $result['cnt'] ?? 0;
    }
}
