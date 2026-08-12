<?php
class BackupController {
    public function index($params = []) {
        Auth::requireAuth();
        $backups = glob(SPM_STORAGE . '/backups/*.tar.gz');
        $list = [];
        foreach ($backups as $file) {
            $list[] = [
                'name' => basename($file),
                'size' => filesize($file),
                'date' => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }
        echo View::render('backup.index', ['title' => 'Backups', 'backups' => $list]);
    }

    public function create($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $name = 'spm_backup_' . date('Ymd_His') . '.tar.gz';
        $path = SPM_STORAGE . '/backups/' . $name;

        $files = [
            SQUID_CONF,
            DB_PATH,
        ];

        // Add keytab if exists
        $keytabs = glob(SQUID_CONF_DIR . '/*.keytab');
        $files = array_merge($files, $keytabs);

        // Add passwd if exists
        if (file_exists(SQUID_CONF_DIR . '/passwd')) {
            $files[] = SQUID_CONF_DIR . '/passwd';
        }

        $cmd = '/usr/bin/tar -czf ' . escapeshellarg($path) . ' ';
        foreach ($files as $file) {
            if (file_exists($file)) {
                $cmd .= escapeshellarg($file) . ' ';
            }
        }

        exec($cmd . ' 2>&1', $output, $returnCode);

        if ($returnCode === 0) {
            Audit::log('backup_create', "Created backup {$name}");
            View::redirect('/backup');
        } else {
            http_response_code(500);
            die('Backup failed: ' . implode("
", $output));
        }
    }

    public function restore($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $name = $_POST['name'] ?? '';
        $path = SPM_STORAGE . '/backups/' . basename($name);

        if (!file_exists($path)) {
            http_response_code(404);
            die('Backup not found');
        }

        // Validate archive
        exec('/usr/bin/tar -tzf ' . escapeshellarg($path) . ' 2>&1', $output, $returnCode);
        if ($returnCode !== 0) {
            http_response_code(400);
            die('Invalid backup archive');
        }

        // Extract to temp
        $tempDir = SPM_STORAGE . '/tmp/restore_' . time();
        mkdir($tempDir, 0750, true);
        exec('/usr/bin/tar -xzf ' . escapeshellarg($path) . ' -C ' . escapeshellarg($tempDir) . ' 2>&1');

        // Restore files
        $restored = [];
        foreach (glob($tempDir . '/*') as $file) {
            $target = basename($file);
            if ($target === 'spm.db') {
                copy($file, DB_PATH);
                $restored[] = 'database';
            } elseif ($target === 'squid.conf') {
                copy($file, SQUID_CONF);
                $restored[] = 'squid.conf';
            } elseif (pathinfo($target, PATHINFO_EXTENSION) === 'keytab') {
                copy($file, SQUID_CONF_DIR . '/' . $target);
                chmod(SQUID_CONF_DIR . '/' . $target, 0600);
                $restored[] = $target;
            }
        }

        // Cleanup
        array_map('unlink', glob($tempDir . '/*'));
        rmdir($tempDir);

        Audit::log('backup_restore', "Restored from {$name}: " . implode(', ', $restored));
        View::redirect('/backup');
    }

    public function download($params = []) {
        Auth::requireAuth();
        $name = $_GET['name'] ?? '';
        $path = SPM_STORAGE . '/backups/' . basename($name);

        if (!file_exists($path)) {
            http_response_code(404);
            die('Backup not found');
        }

        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . basename($name) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
