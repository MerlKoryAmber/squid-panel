<?php
class CachePeerController {
    public function index($params = []) {
        Auth::requireAuth();
        $peers = Database::fetchAll(
            "SELECT p.*, (SELECT COUNT(*) FROM cache_peer_access_rules r WHERE r.peer_id = p.id) AS rule_count
             FROM cache_peers p ORDER BY id"
        );
        foreach ($peers as &$row) {
            $row['options'] = self::composeOptionsString($row);
        }
        unset($row);
        $creating = isset($_GET['new']);
        $selectedId = (int)($_GET['id'] ?? 0);
        if (!$creating && $selectedId <= 0 && !empty($peers)) {
            $selectedId = (int)$peers[0]['id'];
        }

        $peer = null;
        $accessRules = [];
        if (!$creating && $selectedId > 0) {
            $peer = Database::fetch("SELECT * FROM cache_peers WHERE id = ?", [$selectedId]);
            if ($peer) {
                $peer['options'] = self::composeOptionsString($peer);
                $accessRules = Database::fetchAll(
                    "SELECT * FROM cache_peer_access_rules WHERE peer_id = ? ORDER BY sort_order, id",
                    [$peer['id']]
                );
            }
        }

        $catalog = PolicyAclKind::catalogByName();
        echo View::render('cache_peer.index', [
            'title' => 'Cascade',
            'active' => 'peers',
            'simpleUi' => PolicyUi::isSimple(),
            'peers' => $peers,
            'peer' => $peer,
            'creating' => $creating,
            'accessRules' => $accessRules,
            'routingRules' => Database::fetchAll("SELECT * FROM routing_rules ORDER BY sort_order, id"),
            'cascadeRoutes' => Database::fetchAll("SELECT * FROM cascade_routes ORDER BY sort_order, id"),
            'acls' => Database::fetchAll("SELECT name, type FROM acls ORDER BY name"),
            'catalog' => $catalog,
            'fromLists' => PolicyAclKind::lists('from', $catalog),
            'toLists' => PolicyAclKind::lists('to', $catalog),
            'isAdmin' => Auth::isAdmin(),
        ]);
    }

    public function create($params = []) {
        Auth::requireAdmin();
        View::redirect('/peers?new=1');
    }

    public function store($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        $data = self::peerFromPost();
        if ($data['hostname'] === '') {
            http_response_code(400);
            die('Hostname is required');
        }
        $id = Database::insert(
            "INSERT INTO cache_peers (name, hostname, peer_type, http_port, icp_port, proxy_only, no_query, no_digest, weight, login, connect_timeout, options, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))",
            [
                $data['name'], $data['hostname'], $data['peer_type'],
                $data['http_port'], $data['icp_port'],
                $data['proxy_only'], $data['no_query'], $data['no_digest'],
                $data['weight'], $data['login'], $data['connect_timeout'],
                $data['options'], $data['status'],
            ]
        );
        Audit::log('peer_create', "Created cache_peer {$data['hostname']}");
        View::redirect('/peers?id=' . (int)$id);
    }

    public function edit($params = []) {
        Auth::requireAdmin();
        $id = (int)($_GET['id'] ?? 0);
        View::redirect($id > 0 ? '/peers?id=' . $id : '/peers');
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
        $data = self::peerFromPost($peer);
        Database::query(
            "UPDATE cache_peers SET name=?, hostname=?, peer_type=?, http_port=?, icp_port=?, proxy_only=?, no_query=?, no_digest=?, weight=?, login=?, connect_timeout=?, options=?, status=?, updated_at=datetime('now') WHERE id=?",
            [
                $data['name'], $data['hostname'], $data['peer_type'],
                $data['http_port'], $data['icp_port'],
                $data['proxy_only'], $data['no_query'], $data['no_digest'],
                $data['weight'], $data['login'], $data['connect_timeout'],
                $data['options'], $data['status'],
                $id
            ]
        );
        Audit::log('peer_update', "Updated cache_peer {$peer['hostname']}");
        $left = Database::fetch("SELECT COUNT(*) AS c FROM cascade_routes");
        if ((int)($left['c'] ?? 0) > 0) {
            CascadeRouteCompiler::applyFromDb();
        }
        View::redirect('/peers?id=' . $id);
    }

    public function delete($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $peer = Database::fetch("SELECT hostname FROM cache_peers WHERE id = ?", [$id]);
        if ($peer) {
            Database::query("DELETE FROM cache_peer_access_rules WHERE peer_id = ?", [$id]);
            Database::query("DELETE FROM cascade_routes WHERE peer_id = ?", [$id]);
            Database::query("DELETE FROM cache_peers WHERE id = ?", [$id]);
            Audit::log('peer_delete', "Deleted cache_peer {$peer['hostname']}");
            $left = Database::fetch("SELECT COUNT(*) AS c FROM cascade_routes");
            if ((int)($left['c'] ?? 0) > 0) {
                CascadeRouteCompiler::applyFromDb();
            }
        }
        View::redirect('/peers');
    }

    public function access($params = []) {
        Auth::requireAuth();
        $peerId = (int)($_GET['peer_id'] ?? $_GET['id'] ?? 0);
        View::redirect($peerId > 0 ? '/peers?id=' . $peerId : '/peers');
    }

    public function storeAccess($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        $peerId = (int)($_POST['peer_id'] ?? 0);
        $action = in_array($_POST['action'] ?? '', ['allow', 'deny'], true) ? $_POST['action'] : 'allow';
        $entries = self::aclEntriesFromPost();
        if ($entries === '') {
            http_response_code(400);
            die('Select at least one ACL');
        }
        $peer = Database::fetch("SELECT hostname FROM cache_peers WHERE id = ?", [$peerId]);
        if (!$peer) {
            http_response_code(404);
            die('Peer not found');
        }
        $maxOrder = Database::fetch(
            "SELECT MAX(sort_order) as max FROM cache_peer_access_rules WHERE peer_id = ?",
            [$peerId]
        );
        $tokens = preg_split('/\s+/', $entries);
        $firstAcl = $tokens[0] ?? '';
        $isNegated = (strpos($firstAcl, '!') === 0);
        if ($isNegated) {
            $firstAcl = substr($firstAcl, 1);
        }
        Database::query(
            "INSERT INTO cache_peer_access_rules (peer_id, hostname, acl_name, acl_entries, action, negated, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'))",
            [$peerId, $peer['hostname'], $firstAcl, $entries, $action, $isNegated ? 1 : 0, ($maxOrder['max'] ?? 0) + 1]
        );
        PolicyUi::forgetCascadeRoutes();
        if ($action === 'allow' && !empty($_POST['lock_path'])) {
            self::lockPathToPeer($peerId, $entries);
            Audit::log('peer_access_create', "Added allow {$entries} to peer {$peerId} + never_direct + deny on other peers");
        } else {
            Audit::log('peer_access_create', "Added {$action} {$entries} to peer {$peerId}");
        }
        View::redirect('/peers?id=' . $peerId);
    }

    public function deleteAccess($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $peerId = (int)($_POST['peer_id'] ?? 0);
        Database::query("DELETE FROM cache_peer_access_rules WHERE id = ?", [$id]);
        PolicyUi::forgetCascadeRoutes();
        Audit::log('peer_access_delete', "Deleted peer access rule {$id}");
        View::redirect('/peers?id=' . $peerId);
    }

    public function editAccess($params = []) {
        Auth::requireAuth();
        $id = (int)($_GET['id'] ?? 0);
        $rule = Database::fetch("SELECT peer_id FROM cache_peer_access_rules WHERE id = ?", [$id]);
        View::redirect($rule ? '/peers?id=' . (int)$rule['peer_id'] : '/peers');
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
        $action = in_array($_POST['action'] ?? '', ['allow', 'deny'], true) ? $_POST['action'] : $rule['action'];
        $entries = self::aclEntriesFromPost();
        if ($entries === '') {
            $entries = trim($_POST['acl_entries'] ?? '');
        }
        if ($entries === '') {
            http_response_code(400);
            die('Select at least one ACL');
        }
        $tokens = preg_split('/\s+/', $entries);
        $firstAcl = $tokens[0] ?? '';
        $isNegated = (strpos($firstAcl, '!') === 0);
        if ($isNegated) {
            $firstAcl = substr($firstAcl, 1);
        }
        Database::query(
            "UPDATE cache_peer_access_rules SET acl_name = ?, acl_entries = ?, action = ?, negated = ?, updated_at = datetime('now') WHERE id = ?",
            [$firstAcl, $entries, $action, $isNegated ? 1 : 0, $id]
        );
        PolicyUi::forgetCascadeRoutes();
        Audit::log('peer_access_update', "Updated peer access rule {$id}");
        View::redirect('/peers?id=' . (int)$rule['peer_id']);
    }

    public function reorderAccess($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        $data = View::jsonInput();
        $peerId = (int)($data['peer_id'] ?? $_POST['peer_id'] ?? 0);
        $order = $data['order'] ?? $_POST['order'] ?? [];
        if (!is_array($order)) {
            $order = [];
        }
        foreach ($order as $index => $id) {
            Database::query(
                "UPDATE cache_peer_access_rules SET sort_order = ? WHERE id = ? AND peer_id = ?",
                [$index + 1, (int)$id, $peerId]
            );
        }
        PolicyUi::forgetCascadeRoutes();
        Audit::log('peer_access_reorder', "Reordered access rules for peer {$peerId}");
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

    public function routing($params = []) {
        Auth::requireAuth();
        View::redirect('/peers#cascade-when');
    }

    public function storeRouting($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        $intent = $_POST['intent'] ?? '';
        $directive = $intent === 'direct' ? 'always_direct' : 'never_direct';
        $action = in_array($_POST['action'] ?? '', ['allow', 'deny'], true) ? $_POST['action'] : 'allow';
        $entries = self::aclEntriesFromPost();
        if ($entries === '') {
            http_response_code(400);
            die('Select at least one ACL');
        }
        $maxOrder = Database::fetch("SELECT MAX(sort_order) as max FROM routing_rules");
        Database::query(
            "INSERT INTO routing_rules (directive, action, acl_name, negated, sort_order, created_at) VALUES (?, ?, ?, 0, ?, datetime('now'))",
            [$directive, $action, $entries, ($maxOrder['max'] ?? 0) + 1]
        );
        PolicyUi::forgetCascadeRoutes();
        Audit::log('routing_create', "{$directive} {$action} {$entries}");
        View::redirect('/peers#cascade-when');
    }

    public function deleteRouting($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        $id = (int)($_POST['id'] ?? 0);
        Database::query("DELETE FROM routing_rules WHERE id = ?", [$id]);
        PolicyUi::forgetCascadeRoutes();
        Audit::log('routing_delete', "Deleted routing rule {$id}");
        View::redirect('/peers#cascade-when');
    }

    public function reorderRouting($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        $data = View::jsonInput();
        $order = $data['order'] ?? $_POST['order'] ?? [];
        if (!is_array($order)) {
            $order = [];
        }
        foreach ($order as $index => $id) {
            Database::query("UPDATE routing_rules SET sort_order = ? WHERE id = ?", [$index + 1, (int)$id]);
        }
        PolicyUi::forgetCascadeRoutes();
        Audit::log('routing_reorder', 'Reordered cascade routing rules');
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

    public function updateRouting($params = []) {
        $this->storeRouting($params);
    }

    public function storeRoute($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        $catalog = PolicyAclKind::catalogByName();
        $from = self::namedListsFromPost('from', $catalog, 'from');
        $to = self::namedListsFromPost('to', $catalog, 'to');
        $tokens = CascadeRouteCompiler::tokens($from, $to);
        if ($tokens === []) {
            http_response_code(400);
            die('Need at least one source or destination list');
        }
        $channel = 'direct';
        $peerId = null;
        $target = (string)($_POST['target'] ?? 'direct');
        if ($target !== 'direct') {
            $channel = 'peer';
            $peerId = (int)$target;
            $peer = Database::fetch("SELECT id FROM cache_peers WHERE id = ?", [$peerId]);
            if (!$peer) {
                http_response_code(400);
                die('Select a peer');
            }
        }
        $maxOrder = Database::fetch("SELECT MAX(sort_order) as max FROM cascade_routes");
        Database::query(
            "INSERT INTO cascade_routes (from_acls, to_acls, channel, peer_id, sort_order, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, datetime('now'), datetime('now'))",
            [json_encode($from), json_encode($to), $channel, $peerId, ($maxOrder['max'] ?? 0) + 1]
        );
        CascadeRouteCompiler::applyFromDb();
        Audit::log('cascade_route_create', "Route {$channel} " . implode(' ', $tokens));
        View::redirect('/peers');
    }

    public function deleteRoute($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        $id = (int)($_POST['id'] ?? 0);
        Database::query("DELETE FROM cascade_routes WHERE id = ?", [$id]);
        CascadeRouteCompiler::applyFromDb();
        Audit::log('cascade_route_delete', "Deleted cascade route {$id}");
        View::redirect('/peers');
    }

    public function reorderRoutes($params = []) {
        Auth::requireAdmin();
        View::verifyCsrf();
        $data = View::jsonInput();
        $order = $data['order'] ?? $_POST['order'] ?? [];
        if (!is_array($order)) {
            $order = [];
        }
        foreach ($order as $index => $id) {
            Database::query("UPDATE cascade_routes SET sort_order = ? WHERE id = ?", [$index + 1, (int)$id]);
        }
        CascadeRouteCompiler::applyFromDb();
        Audit::log('cascade_route_reorder', 'Reordered cascade routes');
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

    private static function namedListsFromPost($field, array $catalog, $wantKind) {
        $raw = $_POST[$field] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }
        $names = [];
        foreach ($raw as $name) {
            $name = trim((string)$name);
            if ($name === '' || !isset($catalog[$name])) {
                continue;
            }
            $kind = PolicyAclKind::kind($catalog[$name]['name'], $catalog[$name]['type'], $catalog[$name]['storage'] ?? 'inline');
            if ($kind === $wantKind) {
                $names[] = $name;
            }
        }
        return array_values(array_unique($names));
    }

    private static function aclEntriesFromPost() {
        $raw = $_POST['acls'] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }
        $names = [];
        foreach ($raw as $name) {
            $name = trim((string)$name);
            if ($name === '' || !preg_match('/^!?[A-Za-z0-9._-]+$/', $name)) {
                continue;
            }
            $names[] = $name;
        }
        return implode(' ', array_unique($names));
    }

    private static function peerFromPost($existing = []) {
        $hostname = trim($_POST['hostname'] ?? ($existing['hostname'] ?? ''));
        $name = trim($_POST['name'] ?? ($existing['name'] ?? ''));
        $peerType = $_POST['peer_type'] ?? ($existing['peer_type'] ?? 'parent');
        if (!in_array($peerType, ['parent', 'sibling', 'multicast'], true)) {
            $peerType = 'parent';
        }
        $httpPort = (int)($_POST['http_port'] ?? $_POST['port'] ?? $existing['http_port'] ?? $existing['port'] ?? 3128);
        if ($httpPort <= 0 || $httpPort > 65535) {
            $httpPort = 3128;
        }
        $icpPort = (int)($_POST['icp_port'] ?? $existing['icp_port'] ?? 0);
        $status = ($_POST['status'] ?? ($existing['status'] ?? 'active')) === 'disabled' ? 'disabled' : 'active';
        $parsed = self::parseOptionTokens($_POST['options'] ?? ($existing['options'] ?? ''));
        return [
            'name' => $name !== '' ? $name : $hostname,
            'hostname' => $hostname,
            'peer_type' => $peerType,
            'http_port' => $httpPort,
            'icp_port' => $icpPort,
            'proxy_only' => $parsed['proxy_only'],
            'no_query' => $parsed['no_query'],
            'no_digest' => $parsed['no_digest'],
            'weight' => $parsed['weight'],
            'login' => $parsed['login'],
            'connect_timeout' => $parsed['connect_timeout'],
            'options' => self::composeOptionsString($parsed + ['options' => implode(' ', $parsed['extra'])]),
            'status' => $status,
        ];
    }

    private static function parseOptionTokens($options) {
        $flags = [
            'proxy_only' => 0,
            'no_query' => 0,
            'no_digest' => 0,
            'login' => '',
            'weight' => 0,
            'connect_timeout' => 0,
            'extra' => [],
        ];
        foreach (preg_split('/\s+/', trim((string)$options)) as $token) {
            if ($token === '') {
                continue;
            }
            if ($token === 'proxy-only') {
                $flags['proxy_only'] = 1;
            } elseif ($token === 'no-query') {
                $flags['no_query'] = 1;
            } elseif ($token === 'no-digest') {
                $flags['no_digest'] = 1;
            } elseif (strpos($token, 'login=') === 0) {
                $flags['login'] = substr($token, 6);
            } elseif (strpos($token, 'weight=') === 0) {
                $flags['weight'] = (int)substr($token, 7);
            } elseif (strpos($token, 'connect-timeout=') === 0) {
                $flags['connect_timeout'] = (int)substr($token, 16);
            } elseif (strpos($token, 'name=') === 0) {
                continue;
            } else {
                $flags['extra'][] = $token;
            }
        }
        return $flags;
    }

    public static function composeOptionsString($peer) {
        $parsed = self::parseOptionTokens($peer['options'] ?? '');
        $parts = [];
        if (!empty($peer['no_query']) || !empty($parsed['no_query'])) {
            $parts[] = 'no-query';
        }
        if (!empty($peer['proxy_only']) || !empty($parsed['proxy_only'])) {
            $parts[] = 'proxy-only';
        }
        if (!empty($peer['no_digest']) || !empty($parsed['no_digest'])) {
            $parts[] = 'no-digest';
        }
        $login = trim((string)($peer['login'] ?? ''));
        if ($login === '') {
            $login = $parsed['login'];
        }
        if ($login !== '') {
            $parts[] = 'login=' . $login;
        }
        $weight = (int)($peer['weight'] ?? 0);
        if ($weight <= 0) {
            $weight = $parsed['weight'];
        }
        if ($weight > 0) {
            $parts[] = 'weight=' . $weight;
        }
        $timeout = (int)($peer['connect_timeout'] ?? 0);
        if ($timeout <= 0) {
            $timeout = $parsed['connect_timeout'];
        }
        if ($timeout > 0) {
            $parts[] = 'connect-timeout=' . $timeout;
        }
        foreach ($parsed['extra'] as $token) {
            $parts[] = $token;
        }
        return implode(' ', array_values(array_unique($parts)));
    }

    private static function lockPathToPeer($peerId, $entries) {
        $exists = Database::fetch(
            "SELECT id FROM routing_rules WHERE directive = 'never_direct' AND action = 'allow' AND acl_name = ?",
            [$entries]
        );
        if (!$exists) {
            $maxOrder = Database::fetch("SELECT MAX(sort_order) as max FROM routing_rules");
            Database::query(
                "INSERT INTO routing_rules (directive, action, acl_name, negated, sort_order, created_at) VALUES ('never_direct', 'allow', ?, 0, ?, datetime('now'))",
                [$entries, ($maxOrder['max'] ?? 0) + 1]
            );
        }

        $others = Database::fetchAll(
            "SELECT id, hostname FROM cache_peers WHERE id != ?",
            [$peerId]
        );
        $tokens = preg_split('/\s+/', $entries);
        $firstAcl = $tokens[0] ?? $entries;
        $isNegated = (strpos($firstAcl, '!') === 0) ? 1 : 0;
        if ($isNegated) {
            $firstAcl = substr($firstAcl, 1);
        }
        foreach ($others as $other) {
            $dup = Database::fetch(
                "SELECT id FROM cache_peer_access_rules WHERE peer_id = ? AND action = 'deny' AND acl_entries = ?",
                [$other['id'], $entries]
            );
            if ($dup) {
                continue;
            }
            Database::query(
                "UPDATE cache_peer_access_rules SET sort_order = sort_order + 1 WHERE peer_id = ?",
                [$other['id']]
            );
            Database::query(
                "INSERT INTO cache_peer_access_rules (peer_id, hostname, acl_name, acl_entries, action, negated, sort_order, created_at) VALUES (?, ?, ?, ?, 'deny', ?, 1, datetime('now'))",
                [$other['id'], $other['hostname'], $firstAcl, $entries, $isNegated]
            );
        }
    }
}
