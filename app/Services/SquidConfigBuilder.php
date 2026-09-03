<?php
/**
 * Generates squid.conf from spm.db. Live file is written by SquidPolicyApply + spmd after parse.
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

    public function fragmentAuth() {
        $lines = [
            '# SPM managed auth_param',
            '',
        ];
        $have = false;
        foreach ($this->config['auth'] ?? [] as $row) {
            $scheme = trim((string)($row['scheme'] ?? ''));
            if ($scheme === '') {
                continue;
            }
            $program = trim((string)($row['program'] ?? ''));
            if ($program !== '') {
                $lines[] = 'auth_param ' . $scheme . ' program ' . $program;
                $have = true;
            }
            $children = (int)($row['children'] ?? 0);
            $extra = trim((string)($row['children_extra'] ?? ''));
            if ($children > 0) {
                $line = 'auth_param ' . $scheme . ' children ' . $children;
                if ($extra !== '') {
                    $line .= ' ' . $extra;
                }
                $lines[] = $line;
                $have = true;
            }
            if ($scheme !== 'negotiate') {
                $realm = trim((string)($row['realm'] ?? ''));
                if ($realm !== '') {
                    $lines[] = 'auth_param ' . $scheme . ' realm ' . $realm;
                    $have = true;
                }
            }
            $ttl = trim((string)($row['credentialsttl'] ?? ''));
            if ($ttl !== '') {
                $lines[] = 'auth_param ' . $scheme . ' credentialsttl ' . $ttl;
                $have = true;
            }
            $keep = trim((string)($row['keep_alive'] ?? ''));
            if ($keep !== '') {
                $lines[] = 'auth_param ' . $scheme . ' keep_alive ' . $keep;
                $have = true;
            }
        }
        if (!$have) {
            return '';
        }
        $lines[] = '';
        return implode("\n", $lines);
    }

    public function fragmentExternalAcl() {
        $lines = [
            '# SPM managed external_acl_type',
            '',
        ];
        $have = false;
        foreach ($this->config['ext_acl'] ?? [] as $row) {
            $name = trim((string)($row['name'] ?? ''));
            $program = trim((string)($row['program'] ?? ''));
            if ($name === '' || $program === '') {
                continue;
            }
            $parts = ['external_acl_type', $name];
            $ttl = (int)($row['ttl'] ?? 3600);
            $neg = (int)($row['negative_ttl'] ?? 60);
            $children = (int)($row['children'] ?? 10);
            $parts[] = 'ttl=' . $ttl;
            $parts[] = 'negative_ttl=' . $neg;
            $parts[] = 'children=' . $children;
            $format = trim((string)($row['format'] ?? '%LOGIN'));
            if ($format === '') {
                $format = '%LOGIN';
            }
            $parts[] = $format;
            $parts[] = $program;
            $opts = trim((string)($row['options'] ?? ''));
            if (strpos($program, 'ext_kerberos_ldap_group_acl') !== false
                || strpos($program, 'kerberos_ldap_group') !== false) {
                $authNeg = null;
                $realm = '';
                foreach ($this->config['auth'] ?? [] as $a) {
                    if (($a['scheme'] ?? '') === 'negotiate') {
                        $authNeg = $a;
                        $realm = strtoupper(trim((string)($a['realm'] ?? '')));
                        break;
                    }
                }
                $opts = AdGroupAcl::withDirectoryAuth($opts, $realm);
                if ($authNeg && strpos($opts, '-S ') === false) {
                    try {
                        $hosts = AdGroupAcl::parseLdapServers((string)($authNeg['ldap_servers'] ?? ''));
                        $opts = AdGroupAcl::withLdapServerList($opts, $hosts, $realm);
                    } catch (Exception $e) {
                        // keep opts
                    }
                }
            }
            if ($opts !== '') {
                $parts[] = $opts;
            }
            $lines[] = implode(' ', $parts);
            $have = true;
        }
        if (!$have) {
            return '';
        }
        $lines[] = '';
        return implode("\n", $lines);
    }

    public function fragmentListen() {
        $g = $this->config['globals'] ?? [];
        $lines = [
            '# SPM managed listen / hostname',
            '',
        ];
        $raw = (string)($g['http_port'] ?? '3128');
        $ports = PanelNet::parseHttpPortLines($raw);
        if (empty($ports)) {
            $ports = ['3128'];
        }
        foreach ($ports as $p) {
            $lines[] = 'http_port ' . $p;
        }
        $host = trim((string)($g['visible_hostname'] ?? ''));
        if ($host !== '') {
            $lines[] = 'visible_hostname ' . $host;
        }
        $icp = trim((string)($g['icp_port'] ?? ''));
        if ($icp !== '') {
            $lines[] = 'icp_port ' . $icp;
        }
        $cacheDir = trim((string)($g['cache_dir'] ?? ''));
        if ($cacheDir !== '') {
            $lines[] = 'cache_dir ' . $cacheDir;
        }
        $dns = trim((string)($g['dns_nameservers'] ?? ''));
        if ($dns !== '') {
            $lines[] = 'dns_nameservers ' . $dns;
        }
        $core = trim((string)($g['coredump_dir'] ?? ''));
        if ($core !== '') {
            $lines[] = 'coredump_dir ' . $core;
        }
        foreach (PanelNet::parseRequestHeaderAccessLines((string)($g['request_header_access'] ?? '')) as $hdr) {
            $lines[] = 'request_header_access ' . $hdr;
        }
        $lines[] = '';
        return implode("\n", $lines);
    }

    public function fragmentExtra() {
        $raw = trim((string)(($this->config['globals']['extra_conf'] ?? '') ?: ''));
        if ($raw === '') {
            return '';
        }
        return "# SPM preserved unmanaged directives\n" . rtrim($raw) . "\n\n";
    }

    public function generate() {
        $ver = defined('SPM_VERSION') ? SPM_VERSION : '0';
        $head = '# Generated by Squid Proxy Manager v' . $ver . "\n"
            . "# Do not edit by hand; panel Save rewrites this file after squid -k parse.\n\n";
        return $head
            . $this->fragmentExtra()
            . $this->fragmentAuth()
            . $this->fragmentExternalAcl()
            . $this->fragmentAcl()
            . $this->fragmentPeers()
            . $this->fragmentHttpAccess()
            . $this->fragmentListen();
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
            'PHP must not write /etc/squid/squid.conf. Stage via SquidPolicyApply, apply via spmd after parse.'
        );
    }
}
