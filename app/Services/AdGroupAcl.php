<?php
/**
 * AD group → Squid ext_kerberos_ldap_group_acl + ACL name.
 */
class AdGroupAcl {
    public const HELPER_BIN = '/usr/lib64/squid/ext_kerberos_ldap_group_acl';
    public const HELPER_PREFIX = 'kg_';
    public const ACL_PREFIX = 'ad_';

    public static function realm() {
        $row = Database::fetch("SELECT realm FROM auth_config WHERE scheme = 'negotiate' LIMIT 1") ?: [];
        $realm = strtoupper(trim((string)($row['realm'] ?? '')));
        return preg_match('/^[A-Z0-9.-]+$/', $realm) ? $realm : '';
    }

    public static function principal() {
        $row = Database::fetch("SELECT principal FROM auth_config WHERE scheme = 'negotiate' LIMIT 1") ?: [];
        return trim((string)($row['principal'] ?? ''));
    }

    public static function normalizeGroup($raw) {
        $raw = trim((string)$raw);
        $raw = str_replace('\\', '/', $raw);
        if (preg_match('#^[^/]+/(.+)$#', $raw, $m)) {
            $raw = $m[1];
        }
        $raw = trim($raw);
        if ($raw === '' || strpos($raw, "\0") !== false) {
            throw new Exception('Empty group name');
        }
        if (preg_match('/[|:;"\'\\\\]/', $raw)) {
            throw new Exception('Group name contains unsupported characters');
        }
        if (strlen($raw) > 256) {
            throw new Exception('Group name too long');
        }
        return $raw;
    }

    public static function ident($group) {
        $s = preg_replace('/[^A-Za-z0-9]+/', '_', $group);
        $s = trim($s, '_');
        if ($s === '') {
            $s = substr(hash('sha256', $group), 0, 12);
        }
        if (strlen($s) > 40) {
            $s = substr($s, 0, 32) . '_' . substr(hash('sha256', $group), 0, 8);
        }
        return $s;
    }

    public static function helperName($group) {
        return self::HELPER_PREFIX . self::ident($group);
    }

    public static function aclName($group) {
        return self::ACL_PREFIX . self::ident($group);
    }

    public static function groupFlag($group, $realm) {
        $ascii = (bool)preg_match('/^[A-Za-z0-9 ._+-]+$/', $group);
        if ($ascii) {
            $arg = $group;
            if ($realm !== '') {
                $arg .= '@' . $realm;
            }
            $flag = '-g';
        } else {
            $arg = bin2hex($group);
            if ($realm !== '') {
                $arg .= '@' . $realm;
            }
            $flag = '-t';
        }
        if (strpbrk($arg, " \t") !== false) {
            return $flag . ' "' . $arg . '"';
        }
        return $flag . ' ' . $arg;
    }

    public static function helperOptions($group, $realm) {
        $parts = ['-m 5'];
        if ($realm !== '') {
            $parts[] = '-D ' . $realm;
        }
        $principal = self::principal();
        if ($principal !== '' && preg_match('/^[A-Za-z0-9\/._@-]+$/', $principal)) {
            $parts[] = '-P ' . $principal;
        }
        $parts[] = self::groupFlag($group, $realm);
        return self::withDirectoryAuth(implode(' ', $parts), $realm);
    }

    /**
     * LDAP hosts for -S / listing.
     * One FQDN per line / comma / space.
     */
    public static function parseLdapServers($raw) {
        $raw = str_replace(["\r\n", "\r"], "\n", (string)$raw);
        $raw = str_replace([',', ';', "\t"], ' ', $raw);
        $out = [];
        $seen = [];
        foreach (preg_split('/[\s]+/', $raw) as $tok) {
            $tok = strtolower(trim($tok));
            if ($tok === '') {
                continue;
            }
            $tok = preg_replace('#^ldap[s]?://#i', '', $tok);
            $tok = preg_replace('#:\d+$#', '', $tok);
            $tok = rtrim($tok, '/');
            if (!preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $tok) || strpos($tok, '..') !== false) {
                throw new Exception('Invalid LDAP server hostname: ' . $tok);
            }
            if (isset($seen[$tok])) {
                continue;
            }
            $seen[$tok] = true;
            $out[] = $tok;
            if (count($out) > 16) {
                throw new Exception('Too many LDAP servers (max 16)');
            }
        }
        return $out;
    }

    public static function ldapServers() {
        $row = Database::fetch("SELECT ldap_servers FROM auth_config WHERE scheme = 'negotiate' LIMIT 1") ?: [];
        try {
            return self::parseLdapServers((string)($row['ldap_servers'] ?? ''));
        } catch (Exception $e) {
            return [];
        }
    }

    public static function storeLdapServers(array $hosts) {
        return implode("\n", $hosts);
    }

    /** Build -S host@REALM:host2@REALM */
    public static function ldapServersFlag(array $hosts, $realm) {
        if (empty($hosts)) {
            return '';
        }
        $realm = strtoupper(trim((string)$realm));
        $parts = [];
        foreach ($hosts as $h) {
            $h = strtolower(trim((string)$h));
            if ($h === '') {
                continue;
            }
            $parts[] = ($realm !== '' && preg_match('/^[A-Z0-9.-]+$/', $realm))
                ? ($h . '@' . $realm)
                : $h;
        }
        if (empty($parts)) {
            return '';
        }
        return '-S ' . implode(':', $parts);
    }

    /** Remove -S/-l/-u/-p/-b (directory contact); keep -g/-t/-m/-D/-P/-a. */
    public static function stripDirectoryAuthFlags($options) {
        $options = trim((string)$options);
        $token = '(?:"[^"]*"|\S+)';
        foreach (['S', 'l', 'u', 'p', 'b'] as $flag) {
            $options = preg_replace('/(?:^|\s)-' . $flag . '\s+' . $token . '/', '', $options);
        }
        return trim(preg_replace('/\s+/', ' ', (string)$options));
    }

    public static function withDirectoryAuth($options, $realm) {
        $options = self::stripDirectoryAuthFlags($options);
        try {
            $flags = AdLdapConfig::helperDirectoryFlags($realm);
        } catch (Exception $e) {
            // LDAP not configured yet — leave group flags (-g/-D) only
            $flags = '';
        }
        if ($flags === '') {
            return $options;
        }
        return $options === '' ? $flags : ($options . ' ' . $flags);
    }

    /** @deprecated use withDirectoryAuth */
    public static function withLdapServerList($options, array $hosts, $realm) {
        $options = self::stripDirectoryAuthFlags($options);
        $flag = self::ldapServersFlag($hosts, $realm);
        if ($flag === '') {
            return $options;
        }
        return $options === '' ? $flag : ($options . ' ' . $flag);
    }

    public static function syncDirectoryOptionsIntoHelpers() {
        // ADR 0010: no live LDAP helper flags; migrate legacy external → proxy_auth files.
        return self::migrateLegacyExternalToProxyAuth();
    }

    /** @deprecated */
    public static function syncLdapServersIntoHelpers(array $hosts, $realm) {
        return self::syncDirectoryOptionsIntoHelpers();
    }

    public static function kdcHost() {
        $hosts = AdLdapConfig::effectiveServers();
        if (!empty($hosts)) {
            return $hosts[0];
        }
        $row = Database::fetch("SELECT kdc FROM auth_config WHERE scheme = 'negotiate' LIMIT 1") ?: [];
        $kdc = strtolower(trim((string)($row['kdc'] ?? '')));
        if (preg_match('/^[a-z0-9.-]+$/', $kdc)) {
            return $kdc;
        }
        return '';
    }

    public static function ldapQueryArgsGssapi() {
        $row = Database::fetch("SELECT keytab_path, realm, principal, kdc FROM auth_config WHERE scheme = 'negotiate' LIMIT 1") ?: [];
        $keytab = PrivilegedExecutor::squidKeytabPath((string)($row['keytab_path'] ?? ''), true);
        $realm = self::realm();
        if ($realm === '') {
            throw new Exception('Set Kerberos realm before listing AD groups');
        }
        $host = self::kdcHost();
        if ($host === '') {
            $host = strtolower($realm);
        }
        $principal = trim((string)($row['principal'] ?? ''));
        if ($principal !== '' && !preg_match('/^[A-Za-z0-9.\/_@-]+$/', $principal)) {
            $principal = '-';
        }
        if ($principal === '') {
            $principal = '-';
        }
        return [basename($keytab), $realm, $host, $principal];
    }

    /** Staging filename for spmd ad_ldap_groups. */
    public static function ldapQueryArgs() {
        return [AdLdapConfig::writeListStaging()];
    }

    public static function listFromDirectory() {
        try {
            $result = PrivilegedExecutor::execute('ad_ldap_groups');
        } catch (Throwable $e) {
            return ['ok' => false, 'groups' => [], 'error' => $e->getMessage()];
        }
        if (!is_array($result)) {
            return ['ok' => false, 'groups' => [], 'error' => 'LDAP group list failed'];
        }
        if (empty($result['success'])) {
            $err = trim((string)(($result['stderr'] ?? '') ?: ($result['error'] ?? '') ?: ($result['stdout'] ?? 'LDAP group list failed')));
            return ['ok' => false, 'groups' => [], 'error' => $err];
        }
        $groups = [];
        foreach (preg_split("/\r\n|\n|\r/", (string)($result['stdout'] ?? '')) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            try {
                $groups[] = self::normalizeGroup($line);
            } catch (Exception $e) {
                continue;
            }
        }
        $groups = array_values(array_unique($groups));
        sort($groups, SORT_NATURAL | SORT_FLAG_CASE);
        if (count($groups) > 2000) {
            $groups = array_slice($groups, 0, 2000);
        }
        return ['ok' => true, 'groups' => $groups, 'error' => ''];
    }

    public static function importedMap() {
        $map = [];
        try {
            $rows = Database::fetchAll(
                "SELECT id, name, group_name FROM acls
                 WHERE group_name IS NOT NULL AND TRIM(group_name) != ''
                   AND (type = 'proxy_auth' OR type = 'external')"
            );
        } catch (Throwable $e) {
            return $map;
        }
        if (!is_array($rows)) {
            return $map;
        }
        foreach ($rows as $row) {
            $key = strtolower(trim((string)$row['group_name']));
            if ($key === '') {
                continue;
            }
            $map[$key] = $row;
        }
        return $map;
    }

    /**
     * Convert legacy kg_* external helpers to proxy_auth file ACLs (ADR 0010).
     * @return int number migrated
     */
    public static function migrateLegacyExternalToProxyAuth() {
        $rows = Database::fetchAll(
            "SELECT id, name, entries, group_name FROM acls
             WHERE type = 'external' AND group_name IS NOT NULL AND TRIM(group_name) != ''"
        );
        if (!is_array($rows) || empty($rows)) {
            return 0;
        }
        $n = 0;
        foreach ($rows as $row) {
            $helpers = json_decode((string)($row['entries'] ?? '[]'), true);
            if (!is_array($helpers)) {
                $helpers = [];
            }
            foreach ($helpers as $hName) {
                $hName = trim((string)$hName);
                if ($hName === '') {
                    continue;
                }
                Database::query('DELETE FROM external_acl_types WHERE name = ?', [$hName]);
            }
            Database::query(
                "UPDATE acls SET type = 'proxy_auth', storage = 'file', entries = '[]', updated_at = datetime('now') WHERE id = ?",
                [(int)$row['id']]
            );
            try {
                AclListFile::writeWorkFile((string)$row['name'], []);
            } catch (Throwable $e) {
                // continue
            }
            $n++;
        }
        // Drop orphaned kg_* helpers that reference kerberos ldap group binary
        $orphans = Database::fetchAll(
            "SELECT id, name FROM external_acl_types WHERE program LIKE ?",
            ['%' . basename(self::HELPER_BIN) . '%']
        );
        foreach ($orphans ?: [] as $o) {
            Database::query('DELETE FROM external_acl_types WHERE id = ?', [(int)$o['id']]);
        }
        return $n;
    }

    public static function ensureImported($group) {
        self::migrateLegacyExternalToProxyAuth();
        $group = self::normalizeGroup($group);
        $aclName = self::aclName($group);
        $existing = Database::fetch("SELECT id, type, storage FROM acls WHERE name = ?", [$aclName]);
        if ($existing) {
            if (($existing['type'] ?? '') !== 'proxy_auth' || ($existing['storage'] ?? '') !== 'file') {
                Database::query(
                    "UPDATE acls SET type='proxy_auth', storage='file', entries='[]', group_name=?, updated_at=datetime('now') WHERE id=?",
                    [$group, (int)$existing['id']]
                );
            }
            return ['id' => (int)$existing['id'], 'name' => $aclName, 'created' => false];
        }
        $n = 2;
        $baseAcl = $aclName;
        while (Database::fetch("SELECT id FROM acls WHERE name = ?", [$aclName])) {
            $aclName = $baseAcl . '_' . $n;
            $n++;
            if ($n > 20) {
                throw new Exception('Could not allocate ACL name for ' . $group);
            }
        }

        AclListFile::writeWorkFile($aclName, []);
        $inst = AclListFile::installLive($aclName);
        if (empty($inst['success'])) {
            $err = trim((string)(($inst['stderr'] ?? '') ?: ($inst['error'] ?? '') ?: 'acl_file_install failed'));
            throw new Exception($err);
        }

        $id = (int)Database::insert(
            "INSERT INTO acls (name, type, entries, storage, description, group_name, created_at, updated_at)
             VALUES (?, 'proxy_auth', '[]', 'file', ?, ?, datetime('now'), datetime('now'))",
            [$aclName, 'AD group ' . $group . ' (synced members)', $group]
        );
        return ['id' => $id, 'name' => $aclName, 'created' => true];
    }
}
