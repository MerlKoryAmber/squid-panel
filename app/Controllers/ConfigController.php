<?php
class ConfigController {
    public function index($params = []) {
        Auth::requireAuth();
        $config = file_exists(SQUID_CONF) ? file_get_contents(SQUID_CONF) : '';
        $globals = Database::fetch("SELECT * FROM squid_globals LIMIT 1") ?: [];
        echo View::render('config.index', ['title' => 'Configuration', 'config' => $config, 'globals' => $globals]);
    }

    public function save($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $content = $_POST['content'] ?? '';

        try {
            $builder = new SquidConfigBuilder();
            $result = $builder->save($content);
            Audit::log('config_save', 'Saved squid.conf configuration');
            View::redirect('/config');
        } catch (Exception $e) {
            http_response_code(400);
            die('Save failed: ' . $e->getMessage());
        }
    }

    public function validate($params = []) {
        Auth::requireAuth();
        header('Content-Type: application/json');

        $content = $_POST['content'] ?? '';
        $tempFile = tempnam(sys_get_temp_dir(), 'squid_test_');
        file_put_contents($tempFile, $content);

        $result = SquidSyntaxChecker::validateFile($tempFile);
        unlink($tempFile);

        echo json_encode($result);
    }

    public function backup($params = []) {
        Auth::requireAuth();
        $backups = glob(SQUID_CONF . '.bak.*');
        rsort($backups);
        header('Content-Type: application/json');
        echo json_encode(array_map('basename', array_slice($backups, 0, 10)));
    }

    public function restore($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $backup = $_POST['backup'] ?? '';
        $path = SQUID_CONF_DIR . '/' . basename($backup);

        if (!file_exists($path)) {
            http_response_code(404);
            die('Backup not found');
        }

        copy($path, SQUID_CONF);
        Audit::log('config_restore', "Restored config from {$backup}");
        View::redirect('/config');
    }
}
