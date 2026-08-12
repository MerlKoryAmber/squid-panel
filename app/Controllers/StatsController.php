<?php
class StatsController {
    public function index($params = []) {
        Auth::requireAuth();
        $stats = LogParser::getStats(SQUID_ACCESS_LOG, 24);
        echo View::render('stats.index', ['title' => 'Statistics', 'stats' => $stats]);
    }

    public function data($params = []) {
        Auth::requireAuth();
        $hours = (int)($_GET['hours'] ?? 24);
        $stats = LogParser::getStats(SQUID_ACCESS_LOG, $hours);
        header('Content-Type: application/json');
        echo json_encode($stats);
    }
}
