<?php
class SettingsController {
    public function index($params = []) {
        Auth::requireAdmin();
        $settings = Database::fetch("SELECT * FROM settings LIMIT 1") ?: [];
        echo View::render('settings.index', ['title' => 'Settings', 'settings' => $settings]);
    }

    public function save($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $lang = in_array($_POST['language'] ?? '', ['ru', 'en']) ? $_POST['language'] : 'ru';
        $theme = $_POST['theme'] ?? 'light';

        Database::query("DELETE FROM settings");
        Database::query(
            "INSERT INTO settings (language, theme, updated_at) VALUES (?, ?, datetime('now'))",
            [$lang, $theme]
        );

        Audit::log('settings_save', "Updated panel settings");
        View::redirect('/settings');
    }
}
