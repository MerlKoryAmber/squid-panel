<?php
class AclController {
    public function index($params = []) {
        Auth::requireAuth();
        $acls = Database::fetchAll("SELECT * FROM acls ORDER BY name, id");
        $types = (require SPM_CONFIG . '/squid.php')['acl_types'];
        echo View::render('acl.index', ['title' => 'ACL Management', 'acls' => $acls, 'types' => $types]);
    }

    public function create($params = []) {
        Auth::requireAdmin();
        $types = (require SPM_CONFIG . '/squid.php')['acl_types'];
        echo View::render('acl.edit', ['title' => 'Create ACL', 'types' => $types, 'acl' => null, 'isAdmin' => true]);
    }

    public function store($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['name'] ?? '');
        $type = $_POST['type'] ?? '';
        $raw = $_POST['entries'] ?? $_POST['values'] ?? '';
        $values = array_values(array_filter(array_map('trim', preg_split('/\R/', $raw)), 'strlen'));
        $description = $_POST['description'] ?? '';
        $group = $_POST['group_name'] ?? '';

        if (empty($name) || empty($type) || empty($values)) {
            http_response_code(400);
            die('Name, type and values are required');
        }

        // Validate values based on type
        foreach ($values as $val) {
            if (!$this->validateAclValue($type, $val)) {
                http_response_code(400);
                die("Invalid value for type {$type}: {$val}");
            }
        }

        Database::query(
            "INSERT INTO acls (name, type, entries, description, group_name, created_at) VALUES (?, ?, ?, ?, ?, datetime('now'))",
            [$name, $type, json_encode(array_values($values)), $description, $group]
        );

        Audit::log('acl_create', "Created ACL {$name} ({$type})");
        View::redirect('/acl');
    }

    public function edit($params = []) {
        Auth::requireAuth();
        $id = (int)($_GET['id'] ?? 0);
        $acl = Database::fetch("SELECT * FROM acls WHERE id = ?", [$id]);
        if (!$acl) {
            http_response_code(404);
            die('ACL not found');
        }
        $types = (require SPM_CONFIG . '/squid.php')['acl_types'];
        echo View::render('acl.edit', [
            'title' => 'Edit ACL',
            'types' => $types,
            'acl' => $acl,
            'isAdmin' => Auth::isAdmin(),
        ]);
    }

    public function update($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        $raw = $_POST['entries'] ?? $_POST['values'] ?? '';
        $values = array_values(array_filter(array_map('trim', preg_split('/\R/', $raw)), 'strlen'));
        $description = $_POST['description'] ?? '';
        $group = $_POST['group_name'] ?? '';

        $acl = Database::fetch("SELECT * FROM acls WHERE id = ?", [$id]);
        if (!$acl) {
            http_response_code(404);
            die('ACL not found');
        }

        foreach ($values as $val) {
            if (!$this->validateAclValue($acl['type'], $val)) {
                http_response_code(400);
                die("Invalid value for type {$acl['type']}: {$val}");
            }
        }

        Database::query(
            "UPDATE acls SET entries = ?, description = ?, group_name = ? WHERE id = ?",
            [json_encode(array_values($values)), $description, $group, $id]
        );

        Audit::log('acl_update', "Updated ACL {$acl['name']}");
        View::redirect('/acl');
    }

    public function delete($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        $acl = Database::fetch("SELECT name FROM acls WHERE id = ?", [$id]);
        if ($acl) {
            Database::query("DELETE FROM acls WHERE id = ?", [$id]);
            Audit::log('acl_delete', "Deleted ACL {$acl['name']}");
        }
        View::redirect('/acl');
    }

    private function validateAclValue($type, $value) {
        switch ($type) {
            case 'src':
            case 'dst':
                return filter_var($value, FILTER_VALIDATE_IP) !== false || 
                       preg_match('/^\d+\.\d+\.\d+\.\d+\/\d+$/', $value) ||
                       preg_match('/^\d+\.\d+\.\d+\.\d+-\d+\.\d+\.\d+\.\d+$/', $value);
            case 'dstdomain':
            case 'srcdomain':
                return preg_match('/^[a-zA-Z0-9.*-]+$/', $value);
            case 'time':
                return preg_match('/^[A-Z]{1,7}\s+\d{2}:\d{2}-\d{2}:\d{2}$/', $value);
            case 'port':
            case 'myport':
                return preg_match('/^\d+(-\d+)?$/', $value);
            case 'url_regex':
            case 'urlpath_regex':
                return @preg_match($value, '') !== false;
            default:
                return strlen($value) > 0;
        }
    }
}
