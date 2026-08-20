<?php
/**
 * Generates squid.conf fragments from structured data.
 * Live /etc/squid/squid.conf is never overwritten here — see SquidPolicyApply + spmd.
 */
class SquidConfigBuilder {
    private $config = [];

    public function loadFromDatabase() {
        $this->config['acls'] = Database::fetchAll("SELECT * FROM acls ORDER BY name, id");
        $this->config['http_access'] = Database::fetchAll("SELECT * FROM http_access_rules ORDER BY sort_order, id");
        $this->config['peers'] = Database::fetchAll("SELECT * FROM cache_peers ORDER BY id");
        $this->config['auth'] = Database::fetchAll("SELECT * FROM auth_config ORDER BY id");
        $this->config['ext_acl'] = Database::fetchAll("SELECT * FROM external_acl_types ORDER BY name, id");
        $this->config['globals'] = Database::fetch("SELECT * FROM squid_globals LIMIT 1") ?: [];
        $this->config['peer_access'] = Database::fetchAll(
            "SELECT cpar.*, cp.hostname AS peer_host, cp.name AS peer_name
             FROM cache_peer_access_rules cpar
             JOIN cache_peers cp ON cpar.peer_id = cp.id
             WHERE COALESCE(cp.status, 'active') = 'active'
             ORDER BY cp.id, cpar.sort_order, cpar.id"
        );
        $this->config['routing'] = Database::fetchAll("SELECT * FROM routing_rules ORDER BY sort_order, id");
        return $this;
    }

    public function loadFromArray(array $config) {
        $this->config = $config;
        return $this;
    }

    public function fragmentAcl() {
        $lines = [
            '# SPM managed ACLs — do not edit; Apply from panel',
            '',
        ];
        foreach ($this->config['acls'] ?? [] as $acl) {
            $name = $acl['name'];
            $type = $acl['type'];
            if (($acl['storage'] ?? 'inline') === 'file') {
                $lines[] = 'acl ' . $name . ' ' . $type . ' ' . AclListFile::squidRef($name);
                continue;
            }
            $values = json_decode($acl['entries'], true) ?: [];
            if ($type === 'external') {
                if (empty($values)) {
                    continue;
                }
                $quoted = [];
                foreach ($values as $val) {
                    $quoted[] = $this->quoteAclToken((string)$val);
                }
                $lines[] = 'acl ' . $name . ' external ' . implode(' ', $quoted);
                continue;
            }
            foreach ($values as $val) {
                $lines[] = 'acl ' . $name . ' ' . $type . ' ' . $this->quoteAclToken((string)$val);
            }
        }
        $lines[] = '';
        return implode("\n", $lines);
    }

    public function fragmentPeers() {
        $lines = [
            '# SPM managed cascade — do not edit; Apply from panel',
            '',
        ];
        foreach ($this->config['peers'] ?? [] as $peer) {
            if (($peer['status'] ?? 'active') === 'disabled') {
                continue;
            }
            $httpPort = $peer['http_port'] ?? $peer['port'] ?? 3128;
            $icpPort = $peer['icp_port'] ?? 0;
            $line = 'cache_peer ' . $peer['hostname'] . ' ' . $peer['peer_type'] . ' ' . $httpPort . ' ' . (int)$icpPort;
            $options = [];
            $seen = [];
            $addOpt = function ($token) use (&$options, &$seen) {
                $token = trim((string)$token);
                if ($token === '' || isset($seen[$token])) {
                    return;
                }
                $seen[$token] = true;
                $options[] = $token;
            };
            if (!empty($peer['proxy_only'])) {
                $addOpt('proxy-only');
            }
            if (!empty($peer['no_query'])) {
                $addOpt('no-query');
            }
            if (!empty($peer['no_digest'])) {
                $addOpt('no-digest');
            }
            if (!empty($peer['weight'])) {
                $addOpt('weight=' . $peer['weight']);
            }
            if (!empty($peer['login'])) {
                $addOpt('login=' . $peer['login']);
            }
            if (!empty($peer['connect_timeout'])) {
                $addOpt('connect-timeout=' . $peer['connect_timeout']);
            }
            $peerName = trim($peer['name'] ?? '');
            if ($peerName !== '') {
                $addOpt('name=' . $peerName);
            }
            foreach (preg_split('/\s+/', trim((string)($peer['options'] ?? ''))) as $extra) {
                if (strpos($extra, 'name=') === 0) {
                    continue;
                }
                $addOpt($extra);
            }
            if (!empty($options)) {
                $line .= ' ' . implode(' ', $options);
            }
            $lines[] = $line;
        }
        $peerAccessRules = $this->config['peer_access'] ?? [];
        if (!empty($peerAccessRules)) {
            $lines[] = '';
            $lines[] = '# cache_peer_access';
            foreach ($peerAccessRules as $rule) {
                $peerRef = trim((string)($rule['peer_name'] ?? ''));
                if ($peerRef === '') {
                    $peerRef = $rule['hostname'] ?: $rule['peer_host'];
                }
                $acls = trim((string)($rule['acl_entries'] !== '' ? $rule['acl_entries'] : $rule['acl_name']));
                $lines[] = 'cache_peer_access ' . $peerRef . ' ' . $rule['action'] . ' ' . $acls;
            }
        }
        $routing = $this->config['routing'] ?? [];
        if (!empty($routing)) {
            $lines[] = '';
            $lines[] = '# never_direct / always_direct';
            foreach ($routing as $rule) {
                $acl = $rule['acl_name'] ?? '';
                if (($rule['negated'] ?? 0) && strpos($acl, ' ') === false && strpos($acl, '!') !== 0) {
                    $acl = '!' . $acl;
                }
                $dir = $rule['directive'] ?? '';
                if ($dir !== 'never_direct' && $dir !== 'always_direct') {
                    continue;
                }
                $lines[] = $dir . ' ' . $rule['action'] . ' ' . $acl;
            }
        }
        $lines[] = '';
        return implode("\n", $lines);
    }

    public function fragmentHttpAccess() {
        $lines = [
            '# SPM managed http_access — do not edit; Apply from panel',
            '',
        ];
        foreach ($this->config['http_access'] ?? [] as $rule) {
            if (isset($rule['enabled']) && (int)$rule['enabled'] === 0) {
                continue;
            }
            $acls = json_decode($rule['acls'], true) ?: [];
            $lines[] = 'http_access ' . $rule['action'] . ' ' . implode(' ', $acls);
        }
        $lines[] = 'http_access deny all';
        $lines[] = '';
        return implode("\n", $lines);
    }

    public function generate() {
        $lines = [];
        $lines[] = '# Generated by Squid Proxy Manager v' . SPM_VERSION . ' (preview only)';
        $lines[] = '# Live Squid is updated only via Apply to Squid (include files).';
        $lines[] = '';
        return implode("\n", $lines)
            . $this->fragmentAcl()
            . $this->fragmentPeers()
            . $this->fragmentHttpAccess();
    }

    private function quoteAclToken($val) {
        $val = trim($val);
        if ($val === '' || strpos($val, '"') !== false) {
            return $val;
        }
        if (strpbrk($val, " \t") !== false) {
            return '"' . $val . '"';
        }
        return $val;
    }

    public function save($content = null) {
        throw new Exception(
            'Refusing to overwrite live squid.conf. Use Apply to Squid (include fragments).'
        );
    }
}
