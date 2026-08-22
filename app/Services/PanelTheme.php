<?php
class PanelTheme {
    public static function normalize($raw) {
        $raw = strtolower(trim((string)$raw));
        if ($raw === 'silver' || $raw === 'bronze' || $raw === 'gold') {
            return $raw;
        }
        return 'gold';
    }

    public static function current() {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $row = Database::fetch("SELECT theme FROM settings LIMIT 1");
            $cached = self::normalize($row['theme'] ?? 'gold');
        } catch (Throwable $e) {
            $cached = 'gold';
        }
        return $cached;
    }
}
