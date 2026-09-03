<?php
/**
 * Dedicated LDAP(S) settings for AD group list + Squid group helper (ADR 0006).
 */
class AdLdapConfig {
    public const MODE_GSSAPI = 'gssapi';
    public const MODE_SIMPLE = 'simple';
    public const STAGING = 'ad-ldap-list.json';

    public static function ensureRow() {
        $row = Database::fetch("SELECT id FROM ad_ldap_config LIMIT 1");
        if ($row) {
            return;
        }
        $servers = '';
        $auth = Database::fetch("SELECT ldap_servers, realm FROM auth_config WHERE scheme = 'negotiate' LIMIT 1") ?: [];
        if (!empty($auth['ldap_servers'])) {
            $servers = (string)$auth['ldap_servers'];
        }
        Database::query(
            "INSERT INTO ad_ldap_config (bind_mode, servers, port, use_ssl, bind_dn, bind_password, base_dn, created_at, updated_at)
             VALUES ('simple', ?, 389, 0, '', '', '', datetime('now'), datetime('now'))",
            [$servers]
        );
    }

    public static function get() {
        try {
            self::ensureRow();
            $row = Database::fetch("SELECT * FROM ad_ldap_config LIMIT 1") ?: [];
        } catch (Throwable $e) {
            return [
                'bind_mode' => self::MODE_SIMPLE,
                'servers' => '',
                'port' => 389,
                'use_ssl' => 0,
                'bind_dn' => '',
                'bind_password' => '',
                'base_dn' => '',
                'has_password' => false,
            ];
        }
        return [
            'bind_mode' => (($row['bind_mode'] ?? '') === self::MODE_GSSAPI) ? self::MODE_GSSAPI : self::MODE_SIMPLE,
            'servers' => (string)($row['servers'] ?? ''),
            'port' => max(1, min(65535, (int)($row['port'] ?? 389))),
            'use_ssl' => !empty($row['use_ssl']) ? 1 : 0,
            'bind_dn' => (string)($row['bind_dn'] ?? ''),
            'bind_password' => (string)($row['bind_password'] ?? ''),
            'base_dn' => (string)($row['base_dn'] ?? ''),
            'has_password' => trim((string)($row['bind_password'] ?? '')) !== '',
        ];
    }

    public static function save(array $in) {
        self::ensureRow();
        $mode = (($in['bind_mode'] ?? '') === self::MODE_SIMPLE) ? self::MODE_SIMPLE : self::MODE_GSSAPI;
        $hosts = AdGroupAcl::parseLdapServers((string)($in['servers'] ?? ''));
        $servers = AdGroupAcl::storeLdapServers($hosts);
        $port = (int)($in['port'] ?? 389);
        if ($port < 1 || $port > 65535) {
            throw new Exception('Invalid LDAP port');
        }
        $useSsl = !empty($in['use_ssl']) ? 1 : 0;
        $bindDn = trim((string)($in['bind_dn'] ?? ''));
        $baseDn = trim((string)($in['base_dn'] ?? ''));
        if ($bindDn !== '' && (strpos($bindDn, "\0") !== false || strlen($bindDn) > 512)) {
            throw new Exception('Invalid bind DN');
        }
        if ($baseDn !== '' && (strpos($baseDn, "\0") !== false || strlen($baseDn) > 512 || strpos($baseDn, '"') !== false)) {
            throw new Exception('Invalid base DN');
        }
        $existing = self::get();
        $pass = (string)($in['bind_password'] ?? '');
        if ($pass === '' || $pass === '********') {
            $pass = $existing['bind_password'];
        } else {
            if (strlen($pass) > 256 || preg_match('/[\x00-\x1f\x7f]/', $pass)) {
                throw new Exception('Invalid bind password');
            }
            // Squid -p is a single token in conf — no whitespace/quotes
            if (preg_match('/[\s"\'\\\\]/', $pass)) {
                throw new Exception('Bind password must not contain spaces or quotes (Squid -p limit)');
            }
        }
        if ($mode === self::MODE_SIMPLE) {
            if (empty($hosts)) {
                throw new Exception('LDAP servers required for simple bind');
            }
            if ($bindDn === '' || $pass === '') {
                throw new Exception('Bind DN and password required for simple bind');
            }
        }
        Database::query(
            "UPDATE ad_ldap_config SET bind_mode=?, servers=?, port=?, use_ssl=?, bind_dn=?, bind_password=?, base_dn=?, updated_at=datetime('now')",
            [$mode, $servers, $port, $useSsl, $bindDn, $pass, $baseDn]
        );
        // Keep Kerberos ldap_servers in sync for builder fallback / old UI
        Database::query(
            "UPDATE auth_config SET ldap_servers = ?, updated_at = datetime('now') WHERE scheme = 'negotiate'",
            [$servers]
        );
        return self::get();
    }

    public static function effectiveServers() {
        $cfg = self::get();
        try {
            $hosts = AdGroupAcl::parseLdapServers($cfg['servers']);
        } catch (Exception $e) {
            $hosts = [];
        }
        if (!empty($hosts)) {
            return $hosts;
        }
        return AdGroupAcl::ldapServers();
    }

    public static function baseDn($realm = '') {
        $cfg = self::get();
        $base = trim($cfg['base_dn']);
        if ($base !== '') {
            return $base;
        }
        $realm = strtoupper($realm !== '' ? $realm : AdGroupAcl::realm());
        if ($realm === '' || !preg_match('/^[A-Z0-9.-]+$/', $realm)) {
            return '';
        }
        $parts = [];
        foreach (explode('.', $realm) as $p) {
            if ($p !== '') {
                $parts[] = 'DC=' . $p;
            }
        }
        return implode(',', $parts);
    }

    /** True when simple bind has servers + DN + password. */
    public static function simpleReady(array $cfg = null) {
        $cfg = $cfg ?: self::get();
        try {
            $hosts = AdGroupAcl::parseLdapServers($cfg['servers'] ?? '');
        } catch (Exception $e) {
            return false;
        }
        return !empty($hosts)
            && trim((string)($cfg['bind_dn'] ?? '')) !== ''
            && trim((string)($cfg['bind_password'] ?? '')) !== '';
    }

    /**
     * LDAP simple = primary when chosen and ready; GSSAPI/keytab = reserve.
     */
    public static function effectiveMode(array $cfg = null) {
        $cfg = $cfg ?: self::get();
        if (($cfg['bind_mode'] ?? self::MODE_SIMPLE) === self::MODE_SIMPLE && self::simpleReady($cfg)) {
            return self::MODE_SIMPLE;
        }
        return self::MODE_GSSAPI;
    }

    /** Flags for ext_kerberos_ldap_group_acl beyond -g/-m/-D/-P. */
    public static function helperDirectoryFlags($realm) {
        $cfg = self::get();
        $hosts = self::effectiveServers();
        $parts = [];
        $mode = self::effectiveMode($cfg);
        if ($mode === self::MODE_SIMPLE) {
            $parts[] = '-u ' . self::optArg($cfg['bind_dn']);
            $parts[] = '-p ' . self::optArg($cfg['bind_password']);
            $base = self::baseDn($realm);
            if ($base !== '') {
                $parts[] = '-b ' . self::optArg($base);
            }
            if ($cfg['use_ssl']) {
                $parts[] = '-s';
                $parts[] = '-a';
            }
            if (!empty($hosts)) {
                $scheme = $cfg['use_ssl'] ? 'ldaps' : 'ldap';
                $port = (int)$cfg['port'];
                $parts[] = '-l ' . $scheme . '://' . $hosts[0] . ':' . $port;
            }
        }
        $sFlag = AdGroupAcl::ldapServersFlag($hosts, $realm);
        if ($sFlag !== '') {
            $parts[] = $sFlag;
        }
        return implode(' ', $parts);
    }

    /** Single argv token for squid.conf helper options. */
    private static function optArg($s) {
        $s = trim((string)$s);
        if ($s === '' || strpos($s, "\0") !== false || strpos($s, '"') !== false) {
            throw new Exception('LDAP option token invalid');
        }
        if (!preg_match('/\s/', $s)) {
            return $s;
        }
        return '"' . $s . '"';
    }

    public static function writeListStaging() {
        $cfg = self::get();
        $realm = AdGroupAcl::realm();
        $hosts = self::effectiveServers();
        $mode = self::effectiveMode($cfg);
        $payload = [
            'bind_mode' => $mode,
            'servers' => $hosts,
            'port' => (int)$cfg['port'],
            'use_ssl' => (int)$cfg['use_ssl'],
            'bind_dn' => $cfg['bind_dn'],
            'bind_password' => $cfg['bind_password'],
            'base_dn' => self::baseDn($realm),
            'realm' => $realm,
        ];
        if ($mode === self::MODE_GSSAPI) {
            $args = AdGroupAcl::ldapQueryArgsGssapi();
            $payload['keytab'] = $args[0];
            $payload['ldap_host'] = $args[2];
            $payload['principal'] = $args[3];
            if (empty($payload['servers'])) {
                $payload['servers'] = [$args[2]];
            }
        }
        $dir = (defined('SPM_STORAGE') ? SPM_STORAGE : '/opt/spm/storage') . '/tmp';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new Exception('Cannot create staging directory');
        }
        $path = $dir . '/' . self::STAGING;
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($path, $json) === false) {
            throw new Exception('Cannot write LDAP staging file');
        }
        @chmod($path, 0600);
        return self::STAGING;
    }
}
