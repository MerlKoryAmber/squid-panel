<?php
class CascadeRouteCompiler {
    public static function plan(array $routes, array $peers) {
        $access = [];
        $routing = [];
        $activePeers = [];
        foreach ($peers as $p) {
            if (($p['status'] ?? 'active') === 'active') {
                $activePeers[] = $p;
            }
        }
        $i = 0;
        foreach ($routes as $route) {
            $i++;
            $tokens = self::tokens($route['from'] ?? [], $route['to'] ?? []);
            if ($tokens === []) {
                continue;
            }
            $entries = implode(' ', $tokens);
            $channel = ($route['channel'] ?? '') === 'direct' ? 'direct' : 'peer';
            if ($channel === 'direct') {
                $routing[] = [
                    'directive' => 'always_direct',
                    'action' => 'allow',
                    'acl_name' => $entries,
                    'sort_order' => $i,
                ];
                continue;
            }
            $peerId = (int)($route['peer_id'] ?? 0);
            $target = null;
            foreach ($peers as $p) {
                if ((int)$p['id'] === $peerId) {
                    $target = $p;
                    break;
                }
            }
            if (!$target) {
                continue;
            }
            $access[] = [
                'peer_id' => (int)$target['id'],
                'hostname' => $target['hostname'],
                'acl_name' => $tokens[0],
                'acl_entries' => $entries,
                'action' => 'allow',
                'sort_order' => $i,
            ];
            foreach ($activePeers as $other) {
                if ((int)$other['id'] === (int)$target['id']) {
                    continue;
                }
                $access[] = [
                    'peer_id' => (int)$other['id'],
                    'hostname' => $other['hostname'],
                    'acl_name' => $tokens[0],
                    'acl_entries' => $entries,
                    'action' => 'deny',
                    'sort_order' => $i,
                ];
            }
            $routing[] = [
                'directive' => 'never_direct',
                'action' => 'allow',
                'acl_name' => $entries,
                'sort_order' => $i,
            ];
        }
        return ['access' => $access, 'routing' => $routing];
    }

    public static function tokens(array $from, array $to) {
        $out = [];
        foreach (array_merge($from, $to) as $name) {
            $name = trim((string)$name);
            if ($name === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
                continue;
            }
            $out[$name] = $name;
        }
        return array_values($out);
    }

    public static function applyFromDb() {
        $peers = Database::fetchAll("SELECT id, hostname, status FROM cache_peers ORDER BY id");
        $rows = Database::fetchAll("SELECT * FROM cascade_routes ORDER BY sort_order, id");
        $routes = [];
        foreach ($rows as $row) {
            $from = json_decode($row['from_acls'], true);
            $to = json_decode($row['to_acls'], true);
            $routes[] = [
                'from' => is_array($from) ? $from : [],
                'to' => is_array($to) ? $to : [],
                'channel' => $row['channel'],
                'peer_id' => $row['peer_id'],
            ];
        }
        $plan = self::plan($routes, $peers);
        $pdo = Database::get();
        $pdo->beginTransaction();
        try {
            Database::query("DELETE FROM cache_peer_access_rules");
            Database::query("DELETE FROM routing_rules");
            foreach ($plan['access'] as $a) {
                Database::query(
                    "INSERT INTO cache_peer_access_rules (peer_id, hostname, acl_name, acl_entries, action, negated, sort_order, created_at)
                     VALUES (?, ?, ?, ?, ?, 0, ?, datetime('now'))",
                    [$a['peer_id'], $a['hostname'], $a['acl_name'], $a['acl_entries'], $a['action'], $a['sort_order']]
                );
            }
            foreach ($plan['routing'] as $r) {
                Database::query(
                    "INSERT INTO routing_rules (directive, action, acl_name, negated, sort_order, created_at)
                     VALUES (?, ?, ?, 0, ?, datetime('now'))",
                    [$r['directive'], $r['action'], $r['acl_name'], $r['sort_order']]
                );
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $plan;
    }
}
