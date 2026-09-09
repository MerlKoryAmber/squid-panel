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
                // Kerberos LOGIN case often differs from sAMAccountName dump; -i for proxy_auth.
                if ($type === 'proxy_auth') {
                    $lines[] = 'acl ' . $name . ' proxy_auth -i ' . AclListFile::squidRef($name);
                } else {
                    $lines[] = 'acl ' . $name . ' ' . $type . ' ' . AclListFile::squidRef($name);
                }
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
        foreach ($this->peersForwardingClientIp() as $peer) {
            $peerName = $peer['peer_name'];
            $aclName = $peer['xff_acl'];
            $lines[] = 'acl ' . $aclName . ' peername ' . $peerName;
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
        $routing = $this->orderedRoutingRules();
        if (!empty($routing)) {
            $lines[] = '';
            $lines[] = '# never_direct / always_direct';
            // proxy_auth in never_direct before src/dst → ACCESS_AUTH_REQUIRED; later rules skipped (HIER_DIRECT).
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

    /**
     * Emit order: never_direct without proxy_auth → always_direct → never_direct with proxy_auth.
     * Squid never_direct checklist stops at AUTH_REQUIRED; src ACLs below a proxy_auth allow never run.
     */
    private function orderedRoutingRules() {
        $neverFast = [];
        $neverAuth = [];
        $always = [];
        foreach ($this->config['routing'] ?? [] as $rule) {
            $dir = $rule['directive'] ?? '';
            if ($dir === 'always_direct') {
                $always[] = $rule;
                continue;
            }
            if ($dir !== 'never_direct') {
                continue;
            }
            if ($this->routingAclUsesProxyAuth((string)($rule['acl_name'] ?? ''))) {
                $neverAuth[] = $rule;
            } else {
                $neverFast[] = $rule;
            }
        }
        return array_merge($neverFast, $always, $neverAuth);
    }

    private function routingAclUsesProxyAuth($aclEntries) {
        static $authTypes = ['proxy_auth' => true, 'proxy_auth_regex' => true];
        $byName = [];
        foreach ($this->config['acls'] ?? [] as $acl) {
            $n = (string)($acl['name'] ?? '');
            if ($n !== '') {
                $byName[$n] = (string)($acl['type'] ?? '');
            }
        }
        foreach (preg_split('/\s+/', trim((string)$aclEntries)) as $tok) {
            $tok = ltrim((string)$tok, '!');
            if ($tok === '') {
                continue;
            }
            $type = $byName[$tok] ?? '';
            if (isset($authTypes[$type])) {
                return true;
            }
            if ($tok === 'authenticated_user') {
                return true;
            }
        }
        return false;
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
            // ADR 0010: AD membership is proxy_auth files — do not emit live LDAP group helpers.
            if (strpos($program, 'ext_kerberos_ldap_group_acl') !== false
                || strpos($program, 'kerberos_ldap_group') !== false
                || strpos($program, 'ext_ldap_group_acl') !== false) {
                continue;
            }
            if (preg_match('/(?:^|\s)-[pw]\s+\S+/', $opts)) {
                throw new Exception('Refusing to write LDAP bind password into squid.conf (ADR 0010)');
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
        if ($cacheDir !== '' && empty($g['disable_cache'])) {
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
        foreach ($this->orderedRequestHeaderAccessLines() as $hdr) {
            $lines[] = 'request_header_access ' . $hdr;
        }
        $lines[] = '';
        return implode("\n", $lines);
    }

    /**
     * Peers with forward_client_ip: emit X-Forwarded-For allow peername ACL before deny all.
     * @return list<array{peer_name:string,xff_acl:string}>
     */
    private function peersForwardingClientIp() {
        $out = [];
        $seen = [];
        foreach ($this->config['peers'] ?? [] as $peer) {
            if (($peer['status'] ?? 'active') === 'disabled') {
                continue;
            }
            if (empty($peer['forward_client_ip'])) {
                continue;
            }
            $peerName = trim((string)($peer['name'] ?? ''));
            if ($peerName === '') {
                $peerName = trim((string)($peer['hostname'] ?? ''));
            }
            if ($peerName === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $peerName)) {
                continue;
            }
            if (isset($seen[$peerName])) {
                continue;
            }
            $seen[$peerName] = true;
            $out[] = [
                'peer_name' => $peerName,
                'xff_acl' => 'spm_xff_' . $peerName,
            ];
        }
        return $out;
    }

    /**
     * Settings request_header_access + per-peer XFF allows (fail-closed deny all after).
     * @return list<string>
     */
    private function orderedRequestHeaderAccessLines() {
        $settings = [];
        try {
            $settings = PanelNet::parseRequestHeaderAccessLines(
                (string)(($this->config['globals']['request_header_access'] ?? '') ?: '')
            );
        } catch (Exception $e) {
            $settings = [];
        }
        $xffPeers = $this->peersForwardingClientIp();
        if (empty($xffPeers)) {
            return $settings;
        }
        $out = [];
        foreach ($xffPeers as $peer) {
            $out[] = 'X-Forwarded-For allow ' . $peer['xff_acl'];
        }
        $haveXffDenyAll = false;
        foreach ($settings as $hdr) {
            if (preg_match('/^X-Forwarded-For\s+deny\s+all$/i', $hdr)) {
                $haveXffDenyAll = true;
                continue;
            }
            // Drop duplicate allows for our managed peer ACLs if someone pasted them in Settings.
            if (preg_match('/^X-Forwarded-For\s+allow\s+spm_xff_[A-Za-z0-9._-]+$/i', $hdr)) {
                continue;
            }
            $out[] = $hdr;
        }
        if ($haveXffDenyAll || !empty($xffPeers)) {
            $out[] = 'X-Forwarded-For deny all';
        }
        return $out;
    }

    public function fragmentExtra() {
        $g = $this->config['globals'] ?? [];
        $raw = SquidCachePolicy::stripManagedExtra((string)(($g['extra_conf'] ?? '') ?: ''));
        $parts = [];
        if ($raw !== '') {
            $parts[] = "# SPM preserved unmanaged directives\n" . rtrim($raw);
        }
        if (!empty($g['disable_cache'])) {
            $parts[] = rtrim(SquidCachePolicy::managedBlock());
        }
        if (empty($parts)) {
            return '';
        }
        return implode("\n\n", $parts) . "\n\n";
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
