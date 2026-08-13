<?php
/**
 * Parses existing squid.conf and imports into SPM database
 */
class SquidConfigParser {

    public static function parseAndImport($configPath = '/etc/squid/squid.conf') {
        if (!file_exists($configPath) || !is_readable($configPath)) {
            return ['success' => false, 'error' => 'Config file not found or not readable: ' . $configPath];
        }

        $lines = file($configPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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

        $globals = [];
        $authParams = [];
        $currentAcl = null;

        foreach ($lines as $i => $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            // Handle line continuations (backslash at end)
            while (substr($line, -1) === '\\' && isset($lines[$i + 1])) {
                $line = substr($line, 0, -1) . ' ' . trim($lines[++$i]);
            }

            $tokens = preg_split('/\s+/', $line);
            if (count($tokens) < 2) continue;

            $directive = $tokens[0];

            switch ($directive) {
                case 'acl':
                    $result = self::parseAcl($tokens);
                    if ($result) {
                        self::importAcl($result);
                        $stats['acls']++;
                    }
                    break;

                case 'http_access':
                    $result = self::parseHttpAccess($tokens);
                    if ($result) {
                        self::importHttpAccess($result);
                        $stats['http_access']++;
                    }
                    break;

                case 'cache_peer':
                    $result = self::parseCachePeer($tokens);
                    if ($result) {
                        self::importCachePeer($result);
                        $stats['peers']++;
                    }
                    break;

                case 'cache_peer_access':
                    $results = self::parseCachePeerAccess($tokens);
                    if (is_array($results)) {
                        foreach ($results as $result) {
                            if (is_array($result)) {
                                self::importCachePeerAccess($result);
                                $stats['peer_access']++;
                            }
                        }
                    }
                    break;

                case 'never_direct':
                case 'always_direct':
                case 'prefer_direct':
                    $results = self::parseRouting($tokens);
                    if (is_array($results)) {
                        foreach ($results as $result) {
                            if (is_array($result)) {
                                self::importRouting($result);
                                $stats['routing']++;
                            }
                        }
                    }
                    break;

                case 'auth_param':
                    $result = self::parseAuthParam($tokens);
                    if ($result) {
                        $authParams[] = $result;
                    }
                    break;

                case 'http_port':
                case 'icp_port':
                case 'cache_dir':
                case 'visible_hostname':
                case 'dns_nameservers':
                    $globals[$directive] = implode(' ', array_slice($tokens, 1));
                    $stats['globals']++;
                    break;
            }
        }

        // Import globals
        if (!empty($globals)) {
            self::importGlobals($globals);
        }

        // Import auth params
        if (!empty($authParams)) {
            self::importAuthParams($authParams);
            $stats['auth'] = count($authParams);
        }

        // Import default settings
        self::importDefaultSettings();

        return ['success' => true, 'stats' => $stats];
    }

    private static function parseAcl($tokens) {
        if (count($tokens) < 4) return null;

        $name = $tokens[1];
        $type = $tokens[2];
        $values = array_slice($tokens, 3);

        // Handle quoted values and flags like -i (case insensitive)
        $cleanValues = [];
        foreach ($values as $val) {
            if ($val === '-i') continue; // Skip case-insensitive flag for now
            $cleanValues[] = trim($val, '"\'');
        }

        return [
            'name' => $name,
            'type' => $type,
            'entries' => $cleanValues,
        ];
    }

    private static function importAcl($acl) {
        // Check if ACL already exists
        $existing = Database::fetch("SELECT id FROM acls WHERE name = ? AND type = ?", [$acl['name'], $acl['type']]);
        if ($existing) {
            // Merge values
            $current = Database::fetch("SELECT entries FROM acls WHERE id = ?", [$existing['id']]);
            $currentValues = json_decode($current['entries'], true) ?: [];
            $merged = array_unique(array_merge($currentValues, $acl['entries']));
            Database::query(
                "UPDATE acls SET entries = ? WHERE id = ?",
                [json_encode(array_values($merged)), $existing['id']]
            );
        } else {
            Database::query(
                "INSERT INTO acls (name, type, entries, description, group_name, created_at) VALUES (?, ?, ?, ?, ?, datetime('now'))",
                [$acl['name'], $acl['type'], json_encode($acl['entries']), 'Imported from squid.conf', '']
            );
        }
    }

    private static function parseHttpAccess($tokens) {
        if (count($tokens) < 3) return null;

        $action = $tokens[1];
        if (!in_array($action, ['allow', 'deny'])) return null;

        $acls = array_slice($tokens, 2);

        return [
            'action' => $action,
            'acls' => $acls,
        ];
    }

    private static function importHttpAccess($rule) {
        $maxOrder = Database::fetch("SELECT MAX(sort_order) as max FROM http_access_rules");
        $order = ($maxOrder['max'] ?? 0) + 1;

        Database::query(
            "INSERT INTO http_access_rules (action, acls, description, sort_order, created_at) VALUES (?, ?, ?, ?, datetime('now'))",
            [$rule['action'], json_encode($rule['acls']), 'Imported from squid.conf', $order]
        );
    }

    private static function parseCachePeer($tokens) {
        if (count($tokens) < 4) return null;

        $hostname = $tokens[1];
        $peerType = $tokens[2];
        $httpPort = (int)$tokens[3];
        $icpPort = isset($tokens[4]) && is_numeric($tokens[4]) ? (int)$tokens[4] : 0;

        $options = [];
        $login = '';
        $weight = 0;
        $connectTimeout = 0;
        $proxyOnly = 0;
        $noQuery = 0;
        $noDigest = 0;
        $peerName = '';

        $remaining = array_slice($tokens, 5);

        foreach ($remaining as $token) {
            if (strpos($token, 'login=') === 0) {
                $login = substr($token, 6);
            } elseif (strpos($token, 'weight=') === 0) {
                $weight = (int)substr($token, 7);
            } elseif (strpos($token, 'connect-timeout=') === 0) {
                $connectTimeout = (int)substr($token, 16);
            } elseif (strpos($token, 'name=') === 0) {
                $peerName = substr($token, 5);
            } elseif ($token === 'proxy-only') {
                $proxyOnly = 1;
            } elseif ($token === 'no-query') {
                $noQuery = 1;
            } elseif ($token === 'no-digest') {
                $noDigest = 1;
            } else {
                $options[] = $token;
            }
        }

        return [
            'hostname' => $peerName ?: $hostname,
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
        ];
    }

    private static function importCachePeer($peer) {
        $existing = Database::fetch("SELECT id FROM cache_peers WHERE hostname = ?", [$peer['hostname']]);
        if ($existing) return; // Skip duplicates

        Database::query(
            "INSERT INTO cache_peers (hostname, peer_type, http_port, icp_port, proxy_only, no_query, no_digest, weight, login, connect_timeout, access_acl, options, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))",
            [
                $peer['hostname'], $peer['peer_type'], $peer['http_port'], $peer['icp_port'],
                $peer['proxy_only'], $peer['no_query'], $peer['no_digest'], $peer['weight'],
                $peer['login'], $peer['connect_timeout'], '', $peer['options']
            ]
        );
    }

    private static function parseCachePeerAccess($tokens) {
        if (count($tokens) < 4) return null;

        $hostname = $tokens[1];
        $action = $tokens[2];
        $aclName = $tokens[3];

        if (!in_array($action, ['allow', 'deny'])) return null;

        // Find peer_id by hostname
        $peer = Database::fetch("SELECT id FROM cache_peers WHERE hostname = ?", [$hostname]);
        if (!$peer) {
            // Try to find by hostname in options or other fields
            $peer = Database::fetch("SELECT id FROM cache_peers WHERE hostname LIKE ?", ['%' . $hostname . '%']);
        }
        if (!$peer) {
            // Create a placeholder peer if it doesn't exist (orphan access rule)
            // This handles cases where cache_peer uses IP but access uses hostname
            return [
                'peer_id' => null,
                'hostname' => $hostname,
                'action' => $action,
                'acl_name' => $aclName,
            ];
        }

        return [
            'peer_id' => $peer['id'],
            'hostname' => $hostname,
            'action' => $action,
            'acl_name' => $aclName,
        ];
    }

    private static function importCachePeerAccess($rule) {
        if (!is_array($rule) || empty($rule['peer_id'])) {
            error_log("SPM DEBUG: skipping orphan rule — " . (is_array($rule) ? "peer_id empty for '{$rule['hostname']}'" : "not an array: " . gettype($rule)));
            return;
        }
        $maxOrder = Database::fetch(
            "SELECT MAX(sort_order) as max FROM cache_peer_access_rules WHERE peer_id = ?",
            [$rule['peer_id']]
        );
        $order = ($maxOrder['max'] ?? 0) + 1;

        error_log("SPM DEBUG: inserting peer_access peer_id={$rule['peer_id']} hostname={$rule['hostname']} action={$rule['action']} acl={$rule['acl_name']}");
        Database::query(
            "INSERT INTO cache_peer_access_rules (peer_id, hostname, acl_name, action, negated, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, datetime('now'))",
            [$rule['peer_id'], $rule['hostname'], $rule['acl_name'], $rule['action'], $rule['negated'] ?? 0, $order]
        );
        error_log("SPM DEBUG: inserted OK");
    }

    private static function parseRouting($tokens) {
        error_log("SPM DEBUG parseRouting tokens: " . implode(" | ", $tokens));
        if (count($tokens) < 3) {
            error_log("SPM DEBUG parseRouting: too few tokens (" . count($tokens) . "), skipping");
            return [];
        }

        $directive = $tokens[0];
        $action = $tokens[2]; // never_direct allow ACL1 ACL2

        if (!in_array($action, ['allow', 'deny'])) return [];

        $rules = [];
        for ($i = 3; $i < count($tokens); $i++) {
            $aclName = $tokens[$i];
            if ($aclName === 'all') continue;
            // Handle negations: !ACLname
            $isNegated = false;
            if (strpos($aclName, '!') === 0) {
                $isNegated = true;
                $aclName = substr($aclName, 1);
            }
            $rules[] = [
                'directive' => $directive,
                'action' => $action,
                'acl_name' => $aclName,
                'negated' => $isNegated ? 1 : 0,
            ];
        }

        return $rules;
    }

    private static function importRouting($rule) {
        Database::query(
            "INSERT INTO routing_rules (directive, action, acl_name, negated) VALUES (?, ?, ?, ?)",
            [$rule['directive'], $rule['action'], $rule['acl_name'], $rule['negated'] ?? 0]
        );
    }

    private static function parseAuthParam($tokens) {
        if (count($tokens) < 4) return null;

        $scheme = $tokens[1];
        $param = $tokens[2];
        $value = implode(' ', array_slice($tokens, 3));

        return [
            'scheme' => $scheme,
            'param' => $param,
            'value' => $value,
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

        foreach ($schemes as $scheme => $data) {
            Database::query("DELETE FROM auth_config WHERE scheme = ?", [$scheme]);
            Database::query(
                "INSERT INTO auth_config (scheme, program, children, realm, credentialsttl, keep_alive, created_at) VALUES (?, ?, ?, ?, ?, ?, datetime('now'))",
                [$data['scheme'], $data['program'], $data['children'], $data['realm'], $data['credentialsttl'], $data['keep_alive']]
            );
        }
    }

    private static function importGlobals($globals) {
        Database::query("DELETE FROM squid_globals");
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
            Database::query(
                "INSERT INTO settings (language, theme, updated_at) VALUES ('ru', 'light', datetime('now'))"
            );
        }
    }
}
