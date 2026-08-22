<?php
class PolicyUi {
    public static function simpleUnlocked() {
        $row = Database::fetch("SELECT simple_ui_enabled FROM settings LIMIT 1");
        return (int)($row['simple_ui_enabled'] ?? 0) === 1;
    }

    public static function unlockSimple() {
        $row = Database::fetch("SELECT * FROM settings LIMIT 1");
        if ($row) {
            Database::query(
                "UPDATE settings SET simple_ui_enabled = 1, updated_at = datetime('now')"
            );
        } else {
            Database::query(
                "INSERT INTO settings (language, theme, panel_allow_ips, simple_ui_enabled, updated_at)
                 VALUES ('ru', 'light', '', 1, datetime('now'))"
            );
        }
    }

    public static function mode() {
        if (!self::simpleUnlocked()) {
            $_SESSION['policy_ui'] = 'expert';
            return 'expert';
        }
        if (!empty($_SESSION['policy_ui']) && self::valid($_SESSION['policy_ui'])) {
            return $_SESSION['policy_ui'];
        }
        $user = Auth::user();
        $mode = $user['policy_ui'] ?? 'expert';
        if (!self::valid($mode)) {
            $mode = 'expert';
        }
        $_SESSION['policy_ui'] = $mode;
        return $mode;
    }

    public static function isSimple() {
        return false;
    }

    public static function set($mode) {
        if (!self::valid($mode)) {
            $mode = 'expert';
        }
        if ($mode === 'simple' && !self::simpleUnlocked()) {
            $mode = 'expert';
        }
        $_SESSION['policy_ui'] = $mode;
        $user = Auth::user();
        if ($user) {
            Database::query(
                "UPDATE users SET policy_ui = ?, updated_at = datetime('now') WHERE id = ?",
                [$mode, (int)$user['id']]
            );
        }
        return $mode;
    }

    public static function valid($mode) {
        return $mode === 'simple' || $mode === 'expert';
    }

    public static function safeReturn($url) {
        $url = (string)$url;
        if ($url === '' || isset($url[0]) && $url[0] !== '/') {
            return '/http_access';
        }
        if (strpos($url, '//') !== false || strpos($url, '..') !== false) {
            return '/http_access';
        }
        $path = parse_url($url, PHP_URL_PATH);
        $ok = [
            '/http_access' => true,
            '/http_access/edit' => true,
            '/peers' => true,
        ];
        if (!isset($ok[$path])) {
            return '/http_access';
        }
        return $url;
    }

    public static function forgetCascadeRoutes() {
        Database::query("DELETE FROM cascade_routes");
    }
}
