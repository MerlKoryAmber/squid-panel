<?php
class HttpAccessController {
    public function index($params = []) {
        Auth::requireAuth();
        $rules = Database::fetchAll("SELECT * FROM http_access_rules ORDER BY sort_order, id");
        $acls = Database::fetchAll("SELECT name, type FROM acls ORDER BY name");
        echo View::render('http_access.index', ['title' => 'HTTP Access Rules', 'rules' => $rules, 'acls' => $acls]);
    }

    public function store($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $action = in_array($_POST['action'] ?? '', ['allow', 'deny']) ? $_POST['action'] : 'deny';
        $acls = $_POST['acls'] ?? [];
        $description = $_POST['description'] ?? '';

        if (empty($acls)) {
            http_response_code(400);
            die('At least one ACL required');
        }

        $maxOrder = Database::fetch("SELECT MAX(sort_order) as max FROM http_access_rules");
        $order = ($maxOrder['max'] ?? 0) + 1;

        Database::query(
            "INSERT INTO http_access_rules (action, acls, description, sort_order, created_at) VALUES (?, ?, ?, ?, datetime('now'))",
            [$action, json_encode($acls), $description, $order]
        );

        Audit::log('http_access_create', "Created http_access {$action} " . implode(' ', $acls));
        View::redirect('/http_access');
    }

    public function reorder($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $order = $_POST['order'] ?? [];
        foreach ($order as $index => $id) {
            Database::query("UPDATE http_access_rules SET sort_order = ? WHERE id = ?", [$index + 1, (int)$id]);
        }

        Audit::log('http_access_reorder', 'Reordered HTTP access rules');
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

    public function delete($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        $rule = Database::fetch("SELECT action, acls FROM http_access_rules WHERE id = ?", [$id]);
        if ($rule) {
            Database::query("DELETE FROM http_access_rules WHERE id = ?", [$id]);
            Audit::log('http_access_delete', "Deleted http_access rule {$id}");
        }
        View::redirect('/http_access');
    }
}
