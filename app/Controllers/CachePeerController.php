<?php
class CachePeerController {
    public function index($params = []) {
        Auth::requireAuth();
        $peers = Database::fetchAll("SELECT * FROM cache_peers ORDER BY id");
        $types = (require SPM_CONFIG . '/squid.php')['peer_types'];
        echo View::render('cache_peer.index', ['title' => 'Cache Peers', 'peers' => $peers, 'types' => $types]);
    }

    public function create($params = []) {
        Auth::requireAdmin();
        $types = (require SPM_CONFIG . '/squid.php')['peer_types'];
        $acls = Database::fetchAll("SELECT name FROM acls WHERE type IN ('src', 'dstdomain', 'url_regex', 'time', 'proxy_auth') ORDER BY name");
        echo View::render('cache_peer.edit', ['title' => 'Add Peer', 'types' => $types, 'acls' => $acls, 'peer' => null]);
    }

    public function store($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $data = [
            'name' => $_POST['name'] ?? '',
            'hostname' => $_POST['hostname'] ?? '',
            'peer_type' => $_POST['peer_type'] ?? 'parent',
            'port' => (int)($_POST['port'] ?? 3128),
            'options' => $_POST['options'] ?? '',
            'status' => $_POST['status'] ?? 'active',
        ];

        if (empty($data['hostname'])) {
            http_response_code(400);
            die('Hostname is required');
        }
        if (empty($data['name'])) {
            $data['name'] = $data['hostname'];
        }

        Database::query(
            "INSERT INTO cache_peers (name, hostname, peer_type, port, options, status, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, datetime('now'))",
            array_values($data)
        );

        Audit::log('peer_create', "Created cache_peer {$data['hostname']}");
        View::redirect('/peers');
    }

    public function edit($params = []) {
        Auth::requireAdmin();
        $id = (int)($_GET['id'] ?? 0);
        $peer = Database::fetch("SELECT * FROM cache_peers WHERE id = ?", [$id]);
        if (!$peer) {
            http_response_code(404);
            die('Peer not found');
        }
        $types = (require SPM_CONFIG . '/squid.php')['peer_types'];
        $acls = Database::fetchAll("SELECT name FROM acls ORDER BY name");
        echo View::render('cache_peer.edit', ['title' => 'Edit Peer', 'types' => $types, 'acls' => $acls, 'peer' => $peer]);
    }

    public function update($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        $peer = Database::fetch("SELECT * FROM cache_peers WHERE id = ?", [$id]);
        if (!$peer) {
            http_response_code(404);
            die('Peer not found');
        }

        Database::query(
            "UPDATE cache_peers SET name=?, hostname=?, peer_type=?, port=?, options=?, status=? WHERE id=?",
            [
                $_POST['name'] ?? $peer['name'],
                $_POST['hostname'] ?? $peer['hostname'],
                $_POST['peer_type'] ?? $peer['peer_type'],
                (int)($_POST['port'] ?? $peer['port']),
                $_POST['options'] ?? $peer['options'],
                $_POST['status'] ?? $peer['status'],
                $id
            ]
        );

        Audit::log('peer_update', "Updated cache_peer {$peer['hostname']}");
        View::redirect('/peers');
    }

    public function delete($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        $peer = Database::fetch("SELECT hostname FROM cache_peers WHERE id = ?", [$id]);
        if ($peer) {
            Database::query("DELETE FROM cache_peers WHERE id = ?", [$id]);
            Audit::log('peer_delete', "Deleted cache_peer {$peer['hostname']}");
        }
        View::redirect('/peers');
    }

    /**
     * Peer Access Rules — cache_peer_access per peer
     */
    public function access($params = []) {
        Auth::requireAuth();
        $peerId = (int)($_GET['peer_id'] ?? 0);
        $peer = Database::fetch("SELECT * FROM cache_peers WHERE id = ?", [$peerId]);
        if (!$peer) {
            http_response_code(404);
            die('Peer not found');
        }

        $rules = Database::fetchAll(
            "SELECT * FROM cache_peer_access_rules WHERE peer_id = ? ORDER BY sort_order, id",
            [$peerId]
        );
        $acls = Database::fetchAll("SELECT name, type FROM acls ORDER BY name");

        echo View::render('cache_peer.access', [
            'title' => 'Peer Access: ' . $peer['hostname'],
            'peer' => $peer,
            'rules' => $rules,
            'acls' => $acls
        ]);
    }

    public function storeAccess($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $peerId = (int)($_POST['peer_id'] ?? 0);
        $aclEntries = trim($_POST['acl_entries'] ?? '');
        $action = in_array($_POST['action'] ?? '', ['allow', 'deny']) ? $_POST['action'] : 'allow';

        if (empty($aclEntries)) {
            http_response_code(400);
            die('ACL entries are required');
        }

        $peer = Database::fetch("SELECT hostname FROM cache_peers WHERE id = ?", [$peerId]);
        $hostname = $peer['hostname'] ?? '';

        $maxOrder = Database::fetch(
            "SELECT MAX(sort_order) as max FROM cache_peer_access_rules WHERE peer_id = ?",
            [$peerId]
        );
        $order = ($maxOrder['max'] ?? 0) + 1;

        // First ACL for display/compatibility
        $tokens = preg_split('/\s+/', $aclEntries);
        $firstAcl = $tokens[0] ?? '';
        $isNegated = (strpos($firstAcl, '!') === 0);
        if ($isNegated) $firstAcl = substr($firstAcl, 1);

        Database::query(
            "INSERT INTO cache_peer_access_rules (peer_id, hostname, acl_name, acl_entries, action, negated, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'))",
            [$peerId, $hostname, $firstAcl, $aclEntries, $action, $isNegated ? 1 : 0, $order]
        );

        Audit::log('peer_access_create', "Added {$action} {$aclEntries} to peer {$peerId}");
        View::redirect('/peers/access?peer_id=' . $peerId);
    }

    public function deleteAccess($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        $peerId = (int)($_POST['peer_id'] ?? 0);

        Database::query("DELETE FROM cache_peer_access_rules WHERE id = ?", [$id]);
        Audit::log('peer_access_delete', "Deleted peer access rule {$id}");
        View::redirect('/peers/access?peer_id=' . $peerId);
    }

    public function editAccess($params = []) {
        Auth::requireAuth();
        $id = (int)($_GET['id'] ?? 0);
        $rule = Database::fetch("SELECT * FROM cache_peer_access_rules WHERE id = ?", [$id]);
        if (!$rule) {
            http_response_code(404);
            die('Rule not found');
        }
        $peer = Database::fetch("SELECT * FROM cache_peers WHERE id = ?", [$rule['peer_id']]);
        echo View::render('cache_peer.access_edit', [
            'title' => 'Edit Peer Access Rule',
            'rule' => $rule,
            'peer' => $peer
        ]);
    }

    public function updateAccess($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $id = (int)($_POST['id'] ?? 0);
        $rule = Database::fetch("SELECT * FROM cache_peer_access_rules WHERE id = ?", [$id]);
        if (!$rule) {
            http_response_code(404);
            die('Rule not found');
        }

        $aclEntries = trim($_POST['acl_entries'] ?? '');
        $action = in_array($_POST['action'] ?? '', ['allow', 'deny']) ? $_POST['action'] : $rule['action'];

        if (empty($aclEntries)) {
            http_response_code(400);
            die('ACL entries are required');
        }

        $tokens = preg_split('/\s+/', $aclEntries);
        $firstAcl = $tokens[0] ?? '';
        $isNegated = (strpos($firstAcl, '!') === 0);
        if ($isNegated) $firstAcl = substr($firstAcl, 1);

        Database::query(
            "UPDATE cache_peer_access_rules SET acl_name = ?, acl_entries = ?, action = ?, negated = ?, updated_at = datetime('now') WHERE id = ?",
            [$firstAcl, $aclEntries, $action, $isNegated ? 1 : 0, $id]
        );

        Audit::log('peer_access_update', "Updated peer access rule {$id}");
        View::redirect('/peers/access?peer_id=' . $rule['peer_id']);
    }

    public function reorderAccess($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        $peerId = (int)($_POST['peer_id'] ?? 0);
        $order = $_POST['order'] ?? [];

        foreach ($order as $index => $id) {
            Database::query(
                "UPDATE cache_peer_access_rules SET sort_order = ? WHERE id = ? AND peer_id = ?",
                [$index + 1, (int)$id, $peerId]
            );
        }

        Audit::log('peer_access_reorder', "Reordered access rules for peer {$peerId}");
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

    /**
     * Global Routing Rules — never_direct / always_direct / prefer_direct
     */
    public function routing($params = []) {
        Auth::requireAuth();
        $rules = Database::fetchAll("SELECT * FROM routing_rules ORDER BY id");
        $acls = Database::fetchAll("SELECT name, type FROM acls ORDER BY name");
        echo View::render('cache_peer.routing', ['title' => 'Global Routing Rules', 'rules' => $rules, 'acls' => $acls]);
    }

    public function updateRouting($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();

        Database::query("DELETE FROM routing_rules");

        $directives = $_POST['directive'] ?? [];
        $actions = $_POST['action'] ?? [];
        $acls = $_POST['acl_name'] ?? [];

        for ($i = 0; $i < count($directives); $i++) {
            if (!empty($directives[$i]) && !empty($actions[$i]) && !empty($acls[$i])) {
                Database::query(
                    "INSERT INTO routing_rules (directive, action, acl_name) VALUES (?, ?, ?)",
                    [$directives[$i], $actions[$i], $acls[$i]]
                );
            }
        }

        Audit::log('routing_update', 'Updated global routing rules');
        View::redirect('/peers/routing');
    }
}
