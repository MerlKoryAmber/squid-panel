<?php
class DashboardController {
    public function index($params = []) {
        Auth::requireAuth();

        $dbCount = function ($table) {
            $allowed = [
                'http_access_rules' => true,
                'acls' => true,
                'cache_peers' => true,
                'cache_peer_access_rules' => true,
                'auth_config' => true,
            ];
            if (!isset($allowed[$table])) {
                return 0;
            }
            try {
                $r = Database::fetch("SELECT COUNT(*) as c FROM {$table}");
                return $r ? (int)($r['c'] ?? 0) : 0;
            } catch (Exception $e) {
                return 0;
            }
        };

        $stats = [
            'http_access' => $dbCount('http_access_rules'),
            'acls'        => $dbCount('acls'),
            'peers'       => $dbCount('cache_peers'),
            'peer_access' => $dbCount('cache_peer_access_rules'),
            'auth'        => $dbCount('auth_config'),
        ];

        echo View::render('dashboard', [
            'title' => 'Dashboard',
            'active' => 'dashboard',
            'stats' => $stats,
            'auditLogs' => Audit::getRecent(5),
            'isAdmin' => Auth::isAdmin(),
        ]);
    }

    public function apiStatus($params = []) {
        Auth::requireAuth();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        header('Content-Type: application/json');
        echo json_encode(PrivilegedExecutor::getSquidStatus());
    }

    public function apiStats($params = []) {
        Auth::requireAuth();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        header('Content-Type: application/json');
        echo json_encode(LogParser::getStats(SQUID_ACCESS_LOG, 24));
    }
}
