<?php
class LogController {
    public function index($params = []) {
        Auth::requireAuth();

        $filters = [
            'ip' => trim($_GET['ip'] ?? ''),
            'user' => trim($_GET['user'] ?? ''),
            'status' => trim($_GET['status'] ?? ''),
            'url' => trim($_GET['url'] ?? ''),
            'method' => trim($_GET['method'] ?? ''),
            'peer' => trim($_GET['peer'] ?? ''),
        ];

        $activeFilters = array_filter($filters, static function ($value) {
            return $value !== '';
        });

        if (!empty($filters['peer']) && $filters['peer'] !== 'DIRECT') {
            $peerRow = Database::fetch(
                "SELECT name, hostname FROM cache_peers WHERE name = ?",
                [$filters['peer']]
            );
            if ($peerRow) {
                $activeFilters['peer_hostname'] = $peerRow['hostname'];
            }
        }

        $peers = Database::fetchAll(
            "SELECT name, hostname FROM cache_peers ORDER BY name"
        );

        $logs = LogParser::filter(SQUID_ACCESS_LOG, $activeFilters, 200);

        echo View::render('logs.index', [
            'title' => 'Access Logs',
            'logs' => $logs,
            'filters' => $filters,
            'peers' => $peers,
        ]);
    }

    public function live($params = []) {
        Auth::requireAuth();
        echo View::render('logs.live', ['title' => 'Live Log Tail']);
    }

    public function stream($params = []) {
        Auth::requireAuth();
        // Release session lock immediately — SSE holds connection open
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');

        $lastSize = (int)($_GET['last_size'] ?? 0);
        $file = SQUID_ACCESS_LOG;

        if (!file_exists($file)) {
            echo "data: " . json_encode(['error' => 'Log file not found']) . "

";
            exit;
        }

        $currentSize = filesize($file);
        if ($currentSize > $lastSize) {
            $handle = fopen($file, 'r');
            fseek($handle, $lastSize);
            $lines = [];
            while (($line = fgets($handle)) !== false && count($lines) < 50) {
                $parsed = LogParser::parseLine($line);
                if ($parsed) $lines[] = $parsed;
            }
            fclose($handle);

            echo "data: " . json_encode(['lines' => $lines, 'size' => $currentSize]) . "

";
        } else {
            echo "data: " . json_encode(['lines' => [], 'size' => $currentSize]) . "

";
        }
        exit;
    }

    public function filter($params = []) {
        Auth::requireAuth();
        $filters = json_decode(file_get_contents('php://input'), true) ?: [];
        $filters = array_map(static function ($value) {
            return is_string($value) ? trim($value) : $value;
        }, $filters);

        if (!empty($filters['peer']) && $filters['peer'] !== 'DIRECT') {
            $peerRow = Database::fetch(
                "SELECT name, hostname FROM cache_peers WHERE name = ?",
                [$filters['peer']]
            );
            if ($peerRow) {
                $filters['peer_hostname'] = $peerRow['hostname'];
            }
        }

        $logs = LogParser::filter(SQUID_ACCESS_LOG, array_filter($filters, static function ($value) {
            return $value !== '' && $value !== null;
        }), 500);
        header('Content-Type: application/json');
        echo json_encode($logs);
    }

    public function export($params = []) {
        Auth::requireAuth();

        $filters = [
            'ip' => trim($_GET['ip'] ?? ''),
            'user' => trim($_GET['user'] ?? ''),
            'status' => trim($_GET['status'] ?? ''),
            'url' => trim($_GET['url'] ?? ''),
            'method' => trim($_GET['method'] ?? ''),
            'peer' => trim($_GET['peer'] ?? ''),
        ];

        $activeFilters = array_filter($filters, static function ($value) {
            return $value !== '';
        });

        if (!empty($filters['peer']) && $filters['peer'] !== 'DIRECT') {
            $peerRow = Database::fetch(
                "SELECT name, hostname FROM cache_peers WHERE name = ?",
                [$filters['peer']]
            );
            if ($peerRow) {
                $activeFilters['peer_hostname'] = $peerRow['hostname'];
            }
        }

        $logs = LogParser::filter(SQUID_ACCESS_LOG, $activeFilters, 10000);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="squid_logs_' . date('Ymd_His') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Timestamp', 'Client IP', 'User', 'Status', 'Bytes', 'Method', 'URL', 'Content-Type']);

        foreach ($logs as $log) {
            fputcsv($output, [
                $log['timestamp'],
                $log['client_ip'],
                $log['user'],
                $log['status'],
                $log['bytes'],
                $log['method'],
                $log['url'],
                $log['content_type'],
            ]);
        }
        fclose($output);
        exit;
    }
}
