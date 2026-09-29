<?php
class StatsController {
    public function index($params = []) {
        Auth::requireAuth();
        // Page shell only — heavy log scan via /stats/api/data (do not block navigation).
        echo View::render('stats.index', [
            'title' => 'Statistics',
            'active' => 'stats',
        ]);
    }

    public function data($params = []) {
        Auth::requireAuth();
        @set_time_limit(60);
        $hours = (int)($_GET['hours'] ?? 24);
        $stats = LogParser::getStats(SQUID_ACCESS_LOG, $hours);
        header('Content-Type: application/json');
        echo json_encode($stats);
    }
}
