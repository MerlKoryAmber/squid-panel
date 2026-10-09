<?php
/**
 * Read-only policy map data from spm.db (HTTP Access + Cascade).
 * Does not parse live squid.conf.
 */
class PolicyMapBuilder {
    /**
     * @return array{
     *   http_access: list<array>,
     *   peers: list<array>,
     *   peer_access: list<array>,
     *   routing: list<array>,
     *   acl_index: array<string, array>
     * }
     */
    public static function build() {
        $aclRows = Database::fetchAll("SELECT name, type, storage FROM acls ORDER BY name, id");
        $aclIndex = [];
        foreach ($aclRows as $a) {
            $name = (string)($a['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $aclIndex[$name] = [
                'name' => $name,
                'type' => (string)($a['type'] ?? ''),
                'storage' => (string)($a['storage'] ?? 'inline'),
            ];
        }

        $httpAccess = [];
        foreach (Database::fetchAll("SELECT * FROM http_access_rules ORDER BY sort_order, id") as $i => $row) {
            $acls = json_decode($row['acls'] ?? '[]', true);
            if (!is_array($acls)) {
                $acls = [];
            }
            $acls = array_values(array_filter(array_map('strval', $acls), static function ($n) {
                return $n !== '';
            }));
            $httpAccess[] = [
                'id' => (int)($row['id'] ?? 0),
                'order' => $i + 1,
                'action' => strtolower((string)($row['action'] ?? 'deny')),
                'acls' => $acls,
                'enabled' => (int)($row['enabled'] ?? 1) === 1,
                'description' => (string)($row['description'] ?? ''),
            ];
        }

        $peers = [];
        $peerById = [];
        foreach (Database::fetchAll("SELECT * FROM cache_peers ORDER BY id") as $p) {
            $id = (int)$p['id'];
            $label = trim((string)($p['name'] ?? ''));
            if ($label === '') {
                $label = trim((string)($p['hostname'] ?? ''));
            }
            $item = [
                'id' => $id,
                'name' => $label,
                'hostname' => (string)($p['hostname'] ?? ''),
                'http_port' => (int)($p['http_port'] ?? $p['port'] ?? 3128),
                'status' => (string)($p['status'] ?? 'active'),
                'forward_client_ip' => !empty($p['forward_client_ip']),
            ];
            $peers[] = $item;
            $peerById[$id] = $item;
        }

        $peerAccess = [];
        foreach (Database::fetchAll(
            "SELECT r.*, p.name AS peer_name, p.hostname AS peer_hostname
             FROM cache_peer_access_rules r
             LEFT JOIN cache_peers p ON p.id = r.peer_id
             ORDER BY r.peer_id, r.sort_order, r.id"
        ) as $r) {
            $entries = trim((string)(($r['acl_entries'] ?? '') !== '' ? $r['acl_entries'] : ($r['acl_name'] ?? '')));
            $aclNames = preg_split('/\s+/', $entries, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $peerLabel = trim((string)($r['peer_name'] ?? ''));
            if ($peerLabel === '') {
                $peerLabel = trim((string)($r['peer_hostname'] ?? $r['hostname'] ?? ''));
            }
            $peerAccess[] = [
                'id' => (int)($r['id'] ?? 0),
                'peer_id' => (int)($r['peer_id'] ?? 0),
                'peer_label' => $peerLabel,
                'action' => strtolower((string)($r['action'] ?? 'allow')),
                'acls' => array_values($aclNames),
                'negated' => (int)($r['negated'] ?? 0) === 1,
            ];
        }

        $routing = [];
        foreach (Database::fetchAll("SELECT * FROM routing_rules ORDER BY sort_order, id") as $i => $row) {
            $acl = trim((string)($row['acl_name'] ?? ''));
            $routing[] = [
                'id' => (int)($row['id'] ?? 0),
                'order' => $i + 1,
                'directive' => (string)($row['directive'] ?? ''),
                'action' => strtolower((string)($row['action'] ?? 'allow')),
                'acl' => $acl,
                'acls' => $acl !== '' ? [$acl] : [],
                'negated' => (int)($row['negated'] ?? 0) === 1,
            ];
        }

        return [
            'http_access' => $httpAccess,
            'peers' => $peers,
            'peer_access' => $peerAccess,
            'routing' => $routing,
            'acl_index' => $aclIndex,
        ];
    }
}
