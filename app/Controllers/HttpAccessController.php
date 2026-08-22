<?php
class HttpAccessController {
    public function index($params = []) {
        Auth::requireAuth();
        $rules = Database::fetchAll("SELECT * FROM http_access_rules ORDER BY sort_order, id");
        echo View::render('http_access.index', [
            'title' => 'HTTP Access Rules',
            'active' => 'http_access',
            'rules' => $rules,
            'isAdmin' => Auth::isAdmin(),
        ]);
    }

    public function create($params = []) {
        Auth::requireAdmin();
        echo View::render('http_access.create', [
            'title' => 'Add HTTP Access Rule',
            'active' => 'http_access',
            'acls' => Database::fetchAll("SELECT name, type, entries, storage FROM acls ORDER BY name"),
        ]);
    }

    public function store($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $acls = $_POST['acls'] ?? [];
        if (!is_array($acls)) {
            $acls = [];
        }
        $action = in_array($_POST['action'] ?? '', ['allow', 'deny']) ? $_POST['action'] : 'deny';
        $description = $_POST['description'] ?? '';

        if (empty($acls)) {
            http_response_code(400);
            die('Need at least one ACL');
        }

        $maxOrder = Database::fetch("SELECT MAX(sort_order) as max FROM http_access_rules");
        $order = ($maxOrder['max'] ?? 0) + 1;

        Database::query(
            "INSERT INTO http_access_rules (action, acls, description, enabled, sort_order, created_at) VALUES (?, ?, ?, 1, ?, datetime('now'))",
            [$action, json_encode(array_values($acls)), $description, $order]
        );

        Audit::log('http_access_create', "Created http_access {$action} " . implode(' ', $acls));
        SquidLiveApply::redirect('/http_access');
    }

    public function reorder($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $data = View::jsonInput();
        $order = $data['order'] ?? $_POST['order'] ?? [];
        if (!is_array($order)) {
            $order = [];
        }

        foreach ($order as $index => $id) {
            Database::query("UPDATE http_access_rules SET sort_order = ? WHERE id = ?", [$index + 1, (int)$id]);
        }

        Audit::log('http_access_reorder', 'Reordered HTTP access rules');
        SquidLiveApply::jsonFinish();
    }

    public function edit($params = []) {
        Auth::requireAuth();
        $id = (int)($_GET['id'] ?? 0);
        $rule = Database::fetch("SELECT * FROM http_access_rules WHERE id = ?", [$id]);
        if (!$rule) {
            http_response_code(404);
            die('Rule not found');
        }
        echo View::render('http_access.edit', [
            'title' => 'Edit HTTP Access Rule',
            'active' => 'http_access',
            'rule' => $rule,
            'acls' => Database::fetchAll("SELECT name, type, entries, storage FROM acls ORDER BY name")
        ]);
    }

    public function update($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        $rule = Database::fetch("SELECT * FROM http_access_rules WHERE id = ?", [$id]);
        if (!$rule) {
            http_response_code(404);
            die('Rule not found');
        }

        $acls = $_POST['acls'] ?? [];
        if (!is_array($acls)) {
            $acls = [];
        }
        $action = in_array($_POST['action'] ?? '', ['allow', 'deny']) ? $_POST['action'] : $rule['action'];
        $description = $_POST['description'] ?? '';
        $enabled = (int)($rule['enabled'] ?? 1);

        if (empty($acls)) {
            http_response_code(400);
            die('Need at least one ACL');
        }

        Database::query(
            "UPDATE http_access_rules SET action = ?, acls = ?, description = ?, enabled = ?, updated_at = datetime('now') WHERE id = ?",
            [$action, json_encode(array_values($acls)), $description, $enabled, $id]
        );

        Audit::log('http_access_update', "Updated http_access rule {$id}");
        SquidLiveApply::redirect('/http_access');
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
        SquidLiveApply::redirect('/http_access');
    }

    public function toggle($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $rule = Database::fetch("SELECT id, enabled FROM http_access_rules WHERE id = ?", [$id]);
        if ($rule) {
            $next = ((int)($rule['enabled'] ?? 1) === 1) ? 0 : 1;
            Database::query(
                "UPDATE http_access_rules SET enabled = ?, updated_at = datetime('now') WHERE id = ?",
                [$next, $id]
            );
            Audit::log('http_access_toggle', "Rule {$id} enabled={$next}");
        }
        SquidLiveApply::redirect('/http_access');
    }
}
