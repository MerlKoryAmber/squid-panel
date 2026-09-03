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
            'panelCertPresent' => PanelTls::panelCertPresent(),
            'panelCertSubject' => PanelTls::panelCertSubject(),
            'flashError' => $flashError,
            'flashSuccess' => $flashSuccess,
        ]);
    }

    public function save($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $lang = in_array($_POST['language'] ?? '', ['ru', 'en']) ? $_POST['language'] : 'ru';
        $theme = PanelTheme::normalize($_POST['theme'] ?? 'gold');
        $prev = Database::fetch("SELECT panel_allow_ips, simple_ui_enabled FROM settings LIMIT 1") ?: [];
        $allow = (string)($prev['panel_allow_ips'] ?? '');
        $simpleUi = (int)($prev['simple_ui_enabled'] ?? 0);

        Database::query("DELETE FROM settings");
        Database::query(
            "INSERT INTO settings (language, theme, panel_allow_ips, simple_ui_enabled, updated_at) VALUES (?, ?, ?, ?, datetime('now'))",
            [$lang, $theme, $allow, $simpleUi]
        );

        Audit::log('settings_save', 'Updated panel settings');
        $_SESSION['flash_success'] = 'Config saved';
        View::redirect('/settings');
    }

    public function saveSquid($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        try {
            $http = (string)($_POST['http_port'] ?? '');
            $host = trim((string)($_POST['visible_hostname'] ?? ''));
            $core = trim((string)($_POST['coredump_dir'] ?? ''));
            if ($core !== '' && !preg_match('#^/[A-Za-z0-9._/-]+$#', $core)) {
                throw new Exception('Invalid coredump_dir');
            }
            $hdrJoin = implode("\n", PanelNet::parseRequestHeaderAccessLines((string)($_POST['request_header_access'] ?? '')));
            $ports = PanelNet::parseHttpPortLines($http);
            $portJoin = implode("\n", $ports);

            $existing = Database::fetch("SELECT * FROM squid_globals LIMIT 1");
            if ($existing) {
                Database::query(
                    "UPDATE squid_globals SET http_port = ?, visible_hostname = ?, coredump_dir = ?, request_header_access = ?, updated_at = datetime('now') WHERE id = ?",
                    [$portJoin, $host, $core, $hdrJoin, (int)$existing['id']]
                );
            } else {
                Database::query(
                    "INSERT INTO squid_globals (http_port, visible_hostname, coredump_dir, request_header_access, updated_at) VALUES (?, ?, ?, ?, datetime('now'))",
                    [$portJoin, $host, $core, $hdrJoin]
                );
            }

            if (!SquidLiveApply::remember()) {
                View::redirect('/settings');
                return;
            }
            Audit::log('squid_listen_save', 'http_port ' . str_replace("\n", ', ', $portJoin));
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
            $theme = 'gold';
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
            SquidLiveApply::remember();
            Audit::log('squid_policy_apply', 'full squid.conf');
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        View::redirect($back);
    }

    public function uploadTls($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        try {
            $certFile = $_FILES['tls_cert'] ?? null;
            $keyFile = $_FILES['tls_key'] ?? null;
            if (!is_array($certFile) || (int)($certFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new Exception('PEM certificate upload required');
            }
            if (!is_array($keyFile) || (int)($keyFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new Exception('PEM private key upload required');
            }
            $certTmp = (string)($certFile['tmp_name'] ?? '');
            $keyTmp = (string)($keyFile['tmp_name'] ?? '');
            if ($certTmp === '' || !is_uploaded_file($certTmp) || $keyTmp === '' || !is_uploaded_file($keyTmp)) {
                throw new Exception('Invalid upload');
            }
            $certRaw = file_get_contents($certTmp);
            $keyRaw = file_get_contents($keyTmp);
            if ($certRaw === false || $keyRaw === false) {
                throw new Exception('Cannot read upload');
            }
            PanelTls::assertPemCert($certRaw);
            PanelTls::assertPemKey($keyRaw);
            PanelTls::writeStage(PanelTls::STAGE_CERT, $certRaw);
            PanelTls::writeStage(PanelTls::STAGE_KEY, $keyRaw);
            $result = PrivilegedExecutor::execute('panel_tls_install');
            @unlink(PanelTls::stageDir() . '/' . PanelTls::STAGE_CERT);
            @unlink(PanelTls::stageDir() . '/' . PanelTls::STAGE_KEY);
            if (empty($result['success'])) {
                $err = trim((string)(($result['stderr'] ?? '') ?: ($result['error'] ?? '') ?: ($result['stdout'] ?? 'TLS install failed')));
                throw new Exception($err);
            }
            Audit::log('panel_tls_upload', 'nginx TLS cert replaced');
            $_SESSION['flash_success'] = 'Panel TLS certificate installed; nginx reloaded. Refresh the browser if the new cert warns.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        View::redirect('/settings');
    }
}
