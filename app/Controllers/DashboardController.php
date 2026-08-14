<?php
class DashboardController {
    public function index($params = []) {
        Auth::requireAuth();

        $status = PrivilegedExecutor::getSquidStatus();
        $version = PrivilegedExecutor::getSquidVersion();

        // Get connection count (approximate via netstat or ss)
        $connections = 0;
        $port = 3128;
        $globals = Database::fetch("SELECT http_port FROM squid_globals LIMIT 1");
        if ($globals && !empty($globals['http_port'])) {
            $port = (int)$globals['http_port'];
        }
        $ss = @shell_exec("/usr/sbin/ss -tan | grep :{$port} | wc -l");
        if ($ss !== null) {
            $connections = (int)trim($ss);
        }

        $recentLogs = LogParser::tail(SQUID_ACCESS_LOG, 10);
        $auditLogs = Audit::getRecent(5);

        $logStats = LogParser::getStats(SQUID_ACCESS_LOG, 24);

        // Database counters for dashboard cards
        $stats = [
            'http_access' => Database::fetch("SELECT COUNT(*) as c FROM http_access_rules")['c'] ?? 0,
            'acls'        => Database::fetch("SELECT COUNT(*) as c FROM acls")['c'] ?? 0,
            'peers'       => Database::fetch("SELECT COUNT(*) as c FROM cache_peers")['c'] ?? 0,
            'peer_access' => Database::fetch("SELECT COUNT(*) as c FROM cache_peer_access_rules")['c'] ?? 0,
            'auth'        => Database::fetch("SELECT COUNT(*) as c FROM auth_settings")['c'] ?? 0,
            'scheduler'   => Database::fetch("SELECT COUNT(*) as c FROM scheduler_tasks")['c'] ?? 0,
            'hourly'      => $logStats['hourly'] ?? [],
            'topDomains'  => $logStats['domains'] ?? [],
        ];

        $acls = Database::fetchAll("SELECT * FROM acls");
        $peers = Database::fetchAll("SELECT * FROM cache_peers");

        echo View::render('dashboard', [
            'title' => 'Dashboard',
            'status' => $status,
            'version' => $version,
            'connections' => $connections,
            'recentLogs' => $recentLogs,
            'auditLogs' => $auditLogs,
            'stats' => $stats,
            'acls' => $acls,
            'peers' => $peers,
        ]);
    }

    public function apiStatus($params = []) {
        Auth::requireAuth();
        // Release session lock for AJAX polling endpoints
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
