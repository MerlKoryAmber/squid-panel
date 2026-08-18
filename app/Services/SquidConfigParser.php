<?php
/**
 * Parses existing squid.conf and imports into SPM database
 */
class SquidConfigParser {

    public static function parseAndImport($configPath = '/etc/squid/squid.conf') {
        if (!file_exists($configPath) || !is_readable($configPath)) {
            return ['success' => false, 'error' => 'Config file not found or not readable: ' . $configPath];
        }

        $lines = file($configPath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return ['success' => false, 'error' => 'Failed to read config file'];
        }

        $stats = [
            'acls' => 0,
            'http_access' => 0,
            'peers' => 0,
            'peer_access' => 0,
            'routing' => 0,
            'auth' => 0,
            'globals' => 0,
        ];

        $acls = [];
        $httpAccess = [];
        $peers = [];
        $peerAccess = [];
        $routing = [];
        $globals = [];
        $authParams = [];

        $count = count($lines);
        for ($i = 0; $i < $count; $i++) {
            $line = trim($lines[$i]);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            while (substr($line, -1) === '\\' && isset($lines[$i + 1])) {
                $line = rtrim(substr($line, 0, -1)) . ' ' . trim($lines[++$i]);
            }
            if (preg_match('/\s+#/', $line)) {
                $line = trim(preg_replace('/\s+#.*$/', '', $line));
            }
            if ($line === '') {
                continue;
            }

            $tokens = preg_split('/\s+/', $line);
            if (count($tokens) < 2) {
                continue;
            }

            switch ($tokens[0]) {
                case 'acl':
                    $parsed = self::parseAcl($tokens);
                    if ($parsed) {
                        $acls[] = $parsed;
                    }
                    break;
                case 'http_access':
                    $parsed = self::parseHttpAccess($tokens);
                    if ($parsed) {
                        $httpAccess[] = $parsed;
                    }
                    break;
                case 'cache_peer':
                    $parsed = self::parseCachePeer($tokens);
                    if ($parsed) {
                        $peers[] = $parsed;
                    }
                    break;
                case 'cache_peer_access':
                    $parsed = self::parseCachePeerAccess($tokens);
                    if ($parsed) {
                        $peerAccess[] = $parsed;
                    }
                    break;
                case 'never_direct':
                case 'always_direct':
                    $parsed = self::parseRouting($tokens);
                    if ($parsed) {
                        $routing[] = $parsed;
                    }
                    break;
                case 'auth_param':
                    $parsed = self::parseAuthParam($tokens);
                    if ($parsed) {
                        $authParams[] = $parsed;
                    }
                    break;
                case 'http_port':
                case 'icp_port':
                case 'cache_dir':
                case 'visible_hostname':
                case 'dns_nameservers':
                    $globals[$tokens[0]] = implode(' ', array_slice($tokens, 1));
                    break;
            }
        }

        self::resetImportedTables();

        foreach ($acls as $acl) {
            self::importAcl($acl);
            $stats['acls']++;
        }
        foreach ($httpAccess as $rule) {
            self::importHttpAccess($rule);
            $stats['http_access']++;
        }
        foreach ($peers as $peer) {
            self::importCachePeer($peer);
            $stats['peers']++;
        }
        foreach ($peerAccess as $rule) {
            if (self::importCachePeerAccess($rule)) {
                $stats['peer_access']++;
            }
        }
        foreach ($routing as $rule) {
            self::importRouting($rule);
            $stats['routing']++;
        }
        if (!empty($globals)) {
            self::importGlobals($globals);
            $stats['globals'] = count($globals);
        }
        if (!empty($authParams)) {
            self::importAuthParams($authParams);
            $stats['auth'] = count($authParams);
        }
        self::importDefaultSettings();

        return ['success' => true, 'stats' => $stats];
    }

    private static function resetImportedTables() {
        Database::query("DELETE FROM cache_peer_access_rules");
        Database::query("DELETE FROM cache_peers");
        Database::query("DELETE FROM routing_rules");
        Database::query("DELETE FROM http_access_rules");
        Database::query("DELETE FROM acls");
        Database::query("DELETE FROM auth_config");
        Database::query("DELETE FROM squid_globals");
    }

    private static function parseAcl($tokens) {
        if (count($tokens) < 3) {
            return null;
        }
        $name = $tokens[1];
        $type = $tokens[2];
        $values = array_slice($tokens, 3);
        $cleanValues = [];
        foreach ($values as $val) {
            if ($val === '-i') {
                continue;
            }
            $cleanValues[] = trim($val, '"\'');
        }
        if ($type === 'proxy_auth' && empty($cleanValues)) {
            $cleanValues[] = 'REQUIRED';
        }
        return [
            'name' => $name,
            'type' => $type,
            'entries' => $cleanValues,
        ];
    }

    private static function importAcl($acl) {
        $existing = Database::fetch("SELECT id FROM acls WHERE name = ? AND type = ?", [$acl['name'], $acl['type']]);
        if ($existing) {
            $current = Database::fetch("SELECT entries FROM acls WHERE id = ?", [$existing['id']]);
            $currentValues = json_decode($current['entries'], true) ?: [];
            $merged = array_values(array_unique(array_merge($currentValues, $acl['entries'])));
            Database::query("UPDATE acls SET entries = ? WHERE id = ?", [json_encode($merged), $existing['id']]);
            return;
        }
        Database::query(
            "INSERT INTO acls (name, type, entries, description, group_name, created_at) VALUES (?, ?, ?, ?, ?, datetime('now'))",
            [$acl['name'], $acl['type'], json_encode($acl['entries']), 'Imported from squid.conf', '']
        );
    }

    private static function parseHttpAccess($tokens) {
        if (count($tokens) < 3) {
            return null;
        }
        $action = $tokens[1];
        if (!in_array($action, ['allow', 'deny'], true)) {
            return null;
        }
        return [
            'action' => $action,
            'acls' => array_values(array_slice($tokens, 2)),
        ];
    }

    private static function importHttpAccess($rule) {
        $maxOrder = Database::fetch("SELECT MAX(sort_order) as max FROM http_access_rules");
        Database::query(
            "INSERT INTO http_access_rules (action, acls, description, sort_order, created_at) VALUES (?, ?, ?, ?, datetime('now'))",
            [$rule['action'], json_encode($rule['acls']), 'Imported from squid.conf', ($maxOrder['max'] ?? 0) + 1]
        );
    }

    private static function parseCachePeer($tokens) {
        if (count($tokens) < 4) {
            return null;
        }
        $hostname = $tokens[1];
        $peerType = $tokens[2];
        if (!in_array($peerType, ['parent', 'sibling', 'multicast'], true)) {
            return null;
        }
        $httpPort = (int)$tokens[3];
        $icpPort = isset($tokens[4]) && is_numeric($tokens[4]) ? (int)$tokens[4] : 0;

        $login = '';
        $weight = 0;
        $connectTimeout = 0;
        $proxyOnly = 0;
        $noQuery = 0;
        $noDigest = 0;
        $peerName = '';
        $options = [];

        foreach (array_slice($tokens, 5) as $token) {
            if (strpos($token, 'login=') === 0) {
                $login = substr($token, 6);
                $options[] = $token;
            } elseif (strpos($token, 'weight=') === 0) {
                $weight = (int)substr($token, 7);
                $options[] = $token;
            } elseif (strpos($token, 'connect-timeout=') === 0) {
                $connectTimeout = (int)substr($token, 16);
                $options[] = $token;
            } elseif (strpos($token, 'name=') === 0) {
                $peerName = substr($token, 5);
            } elseif ($token === 'proxy-only') {
                $proxyOnly = 1;
                $options[] = $token;
            } elseif ($token === 'no-query') {
                $noQuery = 1;
                $options[] = $token;
            } elseif ($token === 'no-digest') {
                $noDigest = 1;
                $options[] = $token;
            } else {
                $options[] = $token;
            }
        }

        return [
            'name' => $peerName !== '' ? $peerName : $hostname,
            'hostname' => $hostname,
            'peer_type' => $peerType,
            'http_port' => $httpPort,
            'icp_port' => $icpPort,
            'proxy_only' => $proxyOnly,
            'no_query' => $noQuery,
            'no_digest' => $noDigest,
            'weight' => $weight,
            'login' => $login,
            'connect_timeout' => $connectTimeout,
            'options' => implode(' ', $options),
            'status' => 'active',
        ];
    }

    private static function importCachePeer($peer) {
        Database::query(
            "INSERT INTO cache_peers (name, hostname, peer_type, http_port, icp_port, proxy_only, no_query, no_digest, weight, login, connect_timeout, access_acl, options, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))",
            [
                $peer['name'], $peer['hostname'], $peer['peer_type'], $peer['http_port'], $peer['icp_port'],
                $peer['proxy_only'], $peer['no_query'], $peer['no_digest'], $peer['weight'],
                $peer['login'], $peer['connect_timeout'], '', $peer['options'], $peer['status'] ?? 'active',
            ]
        );
    }

    private static function parseCachePeerAccess($tokens) {
        if (count($tokens) < 4) {
            return null;
        }
        $peerRef = $tokens[1];
        $action = $tokens[2];
        if (!in_array($action, ['allow', 'deny'], true)) {
            return null;
        }
        $aclTokens = array_slice($tokens, 3);
        $aclEntries = trim(implode(' ', $aclTokens));
        if ($aclEntries === '') {
            return null;
        }
        $firstAcl = $aclTokens[0];
        $isNegated = strpos($firstAcl, '!') === 0;
        if ($isNegated) {
            $firstAcl = substr($firstAcl, 1);
        }
        return [
            'peer_ref' => $peerRef,
            'action' => $action,
            'acl_name' => $firstAcl !== '' ? $firstAcl : $aclEntries,
            'acl_entries' => $aclEntries,
            'negated' => $isNegated ? 1 : 0,
        ];
    }

    private static function findPeer($ref) {
        return Database::fetch(
            "SELECT id, hostname, name FROM cache_peers WHERE name = ? OR hostname = ? ORDER BY CASE WHEN name = ? THEN 0 ELSE 1 END LIMIT 1",
            [$ref, $ref, $ref]
        );
    }

    private static function importCachePeerAccess($rule) {
        $peer = self::findPeer($rule['peer_ref']);
        if (!$peer) {
            return false;
        }
        $maxOrder = Database::fetch(
            "SELECT MAX(sort_order) as max FROM cache_peer_access_rules WHERE peer_id = ?",
            [$peer['id']]
        );
        Database::query(
            "INSERT INTO cache_peer_access_rules (peer_id, hostname, acl_name, acl_entries, action, negated, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'))",
            [
                $peer['id'],
                $rule['peer_ref'],
                $rule['acl_name'],
                $rule['acl_entries'],
                $rule['action'],
                $rule['negated'],
                ($maxOrder['max'] ?? 0) + 1,
            ]
        );
        return true;
    }

    private static function parseRouting($tokens) {
        if (count($tokens) < 3) {
            return null;
        }
        $directive = $tokens[0];
        $action = $tokens[1];
        if (!in_array($action, ['allow', 'deny'], true)) {
            return null;
        }
        $acls = array_values(array_slice($tokens, 2));
        if (empty($acls)) {
            return null;
        }
        $entries = implode(' ', $acls);
        $negated = strpos($acls[0], '!') === 0 ? 1 : 0;
        return [
            'directive' => $directive,
            'action' => $action,
            'acl_name' => $entries,
            'negated' => $negated,
        ];
    }

    private static function importRouting($rule) {
        $maxOrder = Database::fetch("SELECT MAX(sort_order) as max FROM routing_rules");
        Database::query(
            "INSERT INTO routing_rules (directive, action, acl_name, negated, sort_order, created_at) VALUES (?, ?, ?, ?, ?, datetime('now'))",
            [$rule['directive'], $rule['action'], $rule['acl_name'], $rule['negated'], ($maxOrder['max'] ?? 0) + 1]
        );
    }

    private static function parseAuthParam($tokens) {
        if (count($tokens) < 4) {
            return null;
        }
        return [
            'scheme' => $tokens[1],
            'param' => $tokens[2],
            'value' => implode(' ', array_slice($tokens, 3)),
        ];
    }

    private static function importAuthParams($params) {
        $schemes = [];
        foreach ($params as $p) {
            $scheme = $p['scheme'];
            if (!isset($schemes[$scheme])) {
                $schemes[$scheme] = [
                    'scheme' => $scheme,
                    'program' => '',
                    'children' => 5,
                    'realm' => 'Squid Proxy',
                    'credentialsttl' => '2 hours',
                    'keep_alive' => 'on',
                ];
            }
            switch ($p['param']) {
                case 'program':
                    $schemes[$scheme]['program'] = $p['value'];
                    break;
                case 'children':
                    $schemes[$scheme]['children'] = (int)$p['value'];
                    break;
                case 'realm':
                    $schemes[$scheme]['realm'] = trim($p['value'], '"\'');
                    break;
                case 'credentialsttl':
                    $schemes[$scheme]['credentialsttl'] = $p['value'];
                    break;
                case 'keep_alive':
                    $schemes[$scheme]['keep_alive'] = $p['value'];
                    break;
            }
        }
        foreach ($schemes as $data) {
            Database::query(
                "INSERT INTO auth_config (scheme, program, children, realm, credentialsttl, keep_alive, created_at) VALUES (?, ?, ?, ?, ?, ?, datetime('now'))",
                [$data['scheme'], $data['program'], $data['children'], $data['realm'], $data['credentialsttl'], $data['keep_alive']]
            );
        }
    }

    private static function importGlobals($globals) {
        Database::query(
            "INSERT INTO squid_globals (http_port, icp_port, cache_dir, visible_hostname, dns_nameservers, updated_at) VALUES (?, ?, ?, ?, ?, datetime('now'))",
            [
                $globals['http_port'] ?? '3128',
                $globals['icp_port'] ?? '3130',
                $globals['cache_dir'] ?? 'ufs /var/spool/squid 100 16 256',
                $globals['visible_hostname'] ?? '',
                $globals['dns_nameservers'] ?? '',
            ]
        );
    }

    private static function importDefaultSettings() {
        $existing = Database::fetch("SELECT id FROM settings LIMIT 1");
        if (!$existing) {
            Database::query("INSERT INTO settings (language, theme, updated_at) VALUES ('ru', 'light', datetime('now'))");
        }
    }
}
