<?php
class HttpAccessController {
    public function index($params = []) {
        Auth::requireAuth();
        $rules = Database::fetchAll("SELECT * FROM http_access_rules ORDER BY sort_order, id");
        $catalog = PolicyAclKind::catalogByName();
        $simple = PolicyUi::isSimple();
        if ($simple) {
            foreach ($rules as &$rule) {
                $rule['_parsed'] = PolicyAclKind::analyze(PolicyAclKind::tokensFromJson($rule['acls']), $catalog);
            }
            unset($rule);
        }
        echo View::render($simple ? 'http_access.simple' : 'http_access.index', [
            'title' => $simple ? 'HTTP rules' : 'HTTP Access Rules',
            'active' => 'http_access',
            'rules' => $rules,
            'acls' => Database::fetchAll("SELECT name, type FROM acls ORDER BY name"),
            'fromLists' => PolicyAclKind::lists('from', $catalog),
            'toLists' => PolicyAclKind::lists('to', $catalog),
            'catalog' => $catalog,
            'isAdmin' => Auth::isAdmin(),
        ]);
    }

    public function store($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        if (PolicyUi::isSimple()) {
            $acls = $this->simpleTokensFromPost();
        } else {
            $acls = $_POST['acls'] ?? [];
        }
        if (!is_array($acls)) {
            $acls = [];
        }
        $action = in_array($_POST['action'] ?? '', ['allow', 'deny']) ? $_POST['action'] : 'deny';
        $description = $_POST['description'] ?? '';

        if (empty($acls)) {
            http_response_code(400);
            die('Need at least one source or destination list');
        }

        $maxOrder = Database::fetch("SELECT MAX(sort_order) as max FROM http_access_rules");
        $order = ($maxOrder['max'] ?? 0) + 1;

        Database::query(
            "INSERT INTO http_access_rules (action, acls, description, sort_order, created_at) VALUES (?, ?, ?, ?, datetime('now'))",
            [$action, json_encode(array_values($acls)), $description, $order]
        );

        Audit::log('http_access_create', "Created http_access {$action} " . implode(' ', $acls));
        View::redirect('/http_access');
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
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

    public function edit($params = []) {
        Auth::requireAuth();
        $id = (int)($_GET['id'] ?? 0);
        $rule = Database::fetch("SELECT * FROM http_access_rules WHERE id = ?", [$id]);
        if (!$rule) {
            http_response_code(404);
            die('Rule not found');
        }
        $catalog = PolicyAclKind::catalogByName();
        $parsed = PolicyAclKind::analyze(PolicyAclKind::tokensFromJson($rule['acls']), $catalog);
        if (PolicyUi::isSimple()) {
            if (!$parsed['simple']) {
                $_SESSION['flash_error'] = 'This rule is not a from/to rule. Use expert mode.';
                View::redirect('/http_access');
            }
            echo View::render('http_access.simple_edit', [
                'title' => 'Edit HTTP rule',
                'active' => 'http_access',
                'rule' => $rule,
                'parsed' => $parsed,
                'fromLists' => PolicyAclKind::lists('from', $catalog),
                'toLists' => PolicyAclKind::lists('to', $catalog),
                'catalog' => $catalog,
            ]);
            return;
        }
        echo View::render('http_access.edit', [
            'title' => 'Edit HTTP Access Rule',
            'active' => 'http_access',
            'rule' => $rule,
            'acls' => Database::fetchAll("SELECT name, type FROM acls ORDER BY name")
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

        if (PolicyUi::isSimple()) {
            $acls = $this->simpleTokensFromPost();
        } else {
            $acls = $_POST['acls'] ?? [];
        }
        if (!is_array($acls)) {
            $acls = [];
        }
        $action = in_array($_POST['action'] ?? '', ['allow', 'deny']) ? $_POST['action'] : $rule['action'];
        $description = $_POST['description'] ?? '';

        if (empty($acls)) {
            http_response_code(400);
            die('Need at least one source or destination list');
        }

        Database::query(
            "UPDATE http_access_rules SET action = ?, acls = ?, description = ?, updated_at = datetime('now') WHERE id = ?",
            [$action, json_encode(array_values($acls)), $description, $id]
        );

        Audit::log('http_access_update', "Updated http_access rule {$id}");
        View::redirect('/http_access');
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

    private function simpleTokensFromPost() {
        $from = $_POST['from'] ?? [];
        $to = $_POST['to'] ?? [];
        if (!is_array($from)) {
            $from = [];
        }
        if (!is_array($to)) {
            $to = [];
        }
        $catalog = PolicyAclKind::catalogByName();
        $names = [];
        foreach (array_merge($from, $to) as $name) {
            $name = trim((string)$name);
            if ($name === '' || !isset($catalog[$name])) {
                continue;
            }
            $kind = PolicyAclKind::kind($catalog[$name]['name'], $catalog[$name]['type'], $catalog[$name]['storage'] ?? 'inline');
            if ($kind === 'from' || $kind === 'to') {
                $names[$name] = $name;
            }
        }
        return array_values($names);
    }
}
