<?php
class SettingsController {
    public function index($params = []) {
        Auth::requireAdmin();
        $settings = Database::fetch("SELECT * FROM settings LIMIT 1") ?: [];
        $globals = Database::fetch("SELECT * FROM squid_globals LIMIT 1") ?: [];
        $flashError = $_SESSION['flash_error'] ?? '';
        $flashSuccess = $_SESSION['flash_success'] ?? '';
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);
        echo View::render('settings.index', [
            'title' => 'Settings',
            'active' => 'settings',
            'settings' => $settings,
            'globals' => $globals,
            'clientIp' => PanelNet::clientIp(),
            'flashError' => $flashError,
            'flashSuccess' => $flashSuccess,
        ]);
    }

    public function save($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $lang = in_array($_POST['language'] ?? '', ['ru', 'en']) ? $_POST['language'] : 'ru';
        $theme = $_POST['theme'] ?? 'light';
        $prev = Database::fetch("SELECT panel_allow_ips, simple_ui_enabled FROM settings LIMIT 1") ?: [];
        $allow = (string)($prev['panel_allow_ips'] ?? '');
        $simpleUi = (int)($prev['simple_ui_enabled'] ?? 0);

        Database::query("DELETE FROM settings");
        Database::query(
            "INSERT INTO settings (language, theme, panel_allow_ips, simple_ui_enabled, updated_at) VALUES (?, ?, ?, ?, datetime('now'))",
            [$lang, $theme, $allow, $simpleUi]
        );

        Audit::log('settings_save', 'Updated panel settings');
        View::redirect('/settings');
    }

    public function saveSquid($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        try {
            $http = (string)($_POST['http_port'] ?? '');
            $host = trim((string)($_POST['visible_hostname'] ?? ''));
            $body = PanelNet::listenFile($http, $host);
            $ports = PanelNet::parseHttpPortLines($http);
            $portJoin = implode("\n", $ports);

            $existing = Database::fetch("SELECT * FROM squid_globals LIMIT 1");
            if ($existing) {
                Database::query(
                    "UPDATE squid_globals SET http_port = ?, visible_hostname = ?, updated_at = datetime('now') WHERE id = ?",
                    [$portJoin, $host, (int)$existing['id']]
                );
            } else {
                Database::query(
                    "INSERT INTO squid_globals (http_port, visible_hostname, updated_at) VALUES (?, ?, datetime('now'))",
                    [$portJoin, $host]
                );
            }

            PanelNet::writeTmp('spm-listen.conf', $body);
            $result = PrivilegedExecutor::execute('squid_listen_apply');
            if (empty($result['success'])) {
                $err = trim((string)(($result['stderr'] ?? '') ?: ($result['error'] ?? '') ?: ($result['stdout'] ?? 'listen apply failed')));
                $_SESSION['flash_error'] = $err;
            } else {
                $_SESSION['flash_success'] = trim((string)($result['stdout'] ?? 'Listen settings applied'));
                Audit::log('squid_listen_save', 'http_port ' . str_replace("\n", ', ', $portJoin));
            }
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        View::redirect('/settings');
    }

    public function saveAllow($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        try {
            $ips = PanelNet::parseAllowList((string)($_POST['panel_allow_ips'] ?? ''));
            $me = PanelNet::clientIp();
            if (!empty($ips) && $me !== '' && PanelNet::validAllowToken($me) && !in_array($me, $ips, true)) {
                array_unshift($ips, $me);
            }
            $body = PanelNet::nginxAllowFile($ips);
            $store = implode("\n", $ips);

            $lang = 'ru';
            $theme = 'light';
            $simpleUi = 0;
            $row = Database::fetch("SELECT * FROM settings LIMIT 1");
            if ($row) {
                $lang = $row['language'] ?? $lang;
                $theme = $row['theme'] ?? $theme;
                $simpleUi = (int)($row['simple_ui_enabled'] ?? 0);
            }
            Database::query("DELETE FROM settings");
            Database::query(
                "INSERT INTO settings (language, theme, panel_allow_ips, simple_ui_enabled, updated_at) VALUES (?, ?, ?, ?, datetime('now'))",
                [$lang, $theme, $store, $simpleUi]
            );

            PanelNet::writeTmp('spm-allow.inc', $body);
            $result = PrivilegedExecutor::execute('nginx_allow_apply');
            if (empty($result['success'])) {
                $err = trim((string)(($result['stderr'] ?? '') ?: ($result['error'] ?? '') ?: ($result['stdout'] ?? 'nginx allow apply failed')));
                $_SESSION['flash_error'] = $err;
            } else {
                $_SESSION['flash_success'] = empty($ips)
                    ? 'Panel allowlist cleared (all IPs). nginx reloaded.'
                    : 'Panel allowlist applied (' . count($ips) . ' entries). nginx reloaded.';
                Audit::log('panel_allow_save', empty($ips) ? 'cleared' : implode(',', $ips));
            }
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        View::redirect('/settings');
    }

    public function applyPolicy($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        $back = (string)($_POST['return'] ?? '/settings');
        $allowBack = ['/settings' => true, '/http_access' => true, '/peers' => true, '/acl' => true];
        if (!isset($allowBack[$back])) {
            $back = '/settings';
        }
        try {
            SquidPolicyApply::stageFromDatabase();
            $result = PrivilegedExecutor::execute('squid_policy_apply');
            if (empty($result['success'])) {
                $err = trim((string)(($result['stderr'] ?? '') ?: ($result['error'] ?? '') ?: ($result['stdout'] ?? 'policy apply failed')));
                $_SESSION['flash_error'] = $err;
            } else {
                $_SESSION['flash_success'] = trim((string)($result['stdout'] ?? 'Policy applied to Squid'));
                Audit::log('squid_policy_apply', 'acl/http_access/cascade includes');
            }
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        View::redirect($back);
    }
}
