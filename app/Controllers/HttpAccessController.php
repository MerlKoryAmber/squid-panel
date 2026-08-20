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
        $editId = (int)($_GET['edit'] ?? 0);
        $drawerAdd = isset($_GET['add']);
        $drawerRule = null;
        if ($simple && $editId > 0) {
            foreach ($rules as $r) {
                if ((int)$r['id'] === $editId) {
                    if (!empty($r['_parsed']['simple'])) {
                        $drawerRule = $r;
                    } else {
                        $_SESSION['flash_error'] = 'This rule is not a from/to rule. Use expert mode.';
                    }
                    break;
                }
            }
        }
        echo View::render($simple ? 'http_access.simple' : 'http_access.index', [
            'title' => $simple ? 'Access rules' : 'HTTP Access Rules',
            'active' => 'http_access',
            'rules' => $rules,
            'acls' => Database::fetchAll("SELECT name, type FROM acls ORDER BY name"),
            'fromLists' => PolicyAclKind::lists('from', $catalog),
            'toLists' => PolicyAclKind::lists('to', $catalog),
            'catalog' => $catalog,
            'isAdmin' => Auth::isAdmin(),
            'drawerAdd' => $simple && $drawerAdd,
            'drawerRule' => $simple ? $drawerRule : null,
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
        $description = PolicyUi::isSimple()
            ? trim((string)($_POST['name'] ?? $_POST['description'] ?? ''))
            : ($_POST['description'] ?? '');
        $enabled = empty($_POST['enabled']) ? 0 : 1;
        if (!PolicyUi::isSimple()) {
            $enabled = 1;
        }

        if (empty($acls)) {
            http_response_code(400);
            die('Need at least one source or destination list');
        }

        $maxOrder = Database::fetch("SELECT MAX(sort_order) as max FROM http_access_rules");
        $order = ($maxOrder['max'] ?? 0) + 1;

        Database::query(
            "INSERT INTO http_access_rules (action, acls, description, enabled, sort_order, created_at) VALUES (?, ?, ?, ?, ?, datetime('now'))",
            [$action, json_encode(array_values($acls)), $description, $enabled, $order]
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
            View::redirect('/http_access?edit=' . $id);
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
        $description = PolicyUi::isSimple()
            ? trim((string)($_POST['name'] ?? $_POST['description'] ?? ''))
            : ($_POST['description'] ?? '');
        $enabled = isset($_POST['enabled']) ? 1 : (int)($rule['enabled'] ?? 1);
        if (!PolicyUi::isSimple()) {
            $enabled = (int)($rule['enabled'] ?? 1);
        }

        if (empty($acls)) {
            http_response_code(400);
            die('Need at least one source or destination list');
        }

        Database::query(
            "UPDATE http_access_rules SET action = ?, acls = ?, description = ?, enabled = ?, updated_at = datetime('now') WHERE id = ?",
            [$action, json_encode(array_values($acls)), $description, $enabled, $id]
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
