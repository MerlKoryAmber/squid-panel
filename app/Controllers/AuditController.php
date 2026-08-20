<?php
class AuditController {
    public function index($params = []) {
        Auth::requireAuth();

        $filters = [
            'user' => $_GET['user'] ?? '',
            'action' => $_GET['action'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ];

        $logs = Audit::getFiltered(array_filter($filters), 100, 0);

        echo View::render('audit.index', [
            'title' => 'Audit Log',
            'logs' => $logs,
            'filters' => $filters,
        ]);
    }
}
