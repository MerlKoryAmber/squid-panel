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
        return implode(' ', $parts);
    }

    public static function kdcHost() {
        $row = Database::fetch("SELECT kdc FROM auth_config WHERE scheme = 'negotiate' LIMIT 1") ?: [];
        $kdc = strtolower(trim((string)($row['kdc'] ?? '')));
        if (preg_match('/^[a-z0-9.-]+$/', $kdc)) {
            return $kdc;
        }
        return '';
    }

    public static function ldapQueryArgs() {
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
            $rows = Database::fetchAll("SELECT id, name, group_name FROM acls WHERE type = 'external'");
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

    public static function ensureImported($group) {
        $group = self::normalizeGroup($group);
        $realm = self::realm();
        $aclName = self::aclName($group);
        $helperName = self::helperName($group);
        $existing = Database::fetch("SELECT id FROM acls WHERE name = ?", [$aclName]);
        if ($existing) {
            return ['id' => (int)$existing['id'], 'name' => $aclName, 'created' => false];
        }
        $n = 2;
        $baseAcl = $aclName;
        $baseHelper = $helperName;
        while (Database::fetch("SELECT id FROM acls WHERE name = ?", [$aclName])
            || Database::fetch("SELECT id FROM external_acl_types WHERE name = ?", [$helperName])) {
            $aclName = $baseAcl . '_' . $n;
            $helperName = $baseHelper . '_' . $n;
            $n++;
            if ($n > 20) {
                throw new Exception('Could not allocate ACL name for ' . $group);
            }
        }

        Database::query(
            "INSERT INTO external_acl_types (name, format, ttl, negative_ttl, children, program, options, created_at, updated_at) VALUES (?, '%LOGIN', 3600, 60, 10, ?, ?, datetime('now'), datetime('now'))",
            [$helperName, self::HELPER_BIN, self::helperOptions($group, $realm)]
        );
        $id = (int)Database::insert(
            "INSERT INTO acls (name, type, entries, storage, description, group_name, created_at, updated_at) VALUES (?, 'external', ?, 'inline', ?, ?, datetime('now'), datetime('now'))",
            [$aclName, json_encode([$helperName]), 'AD group ' . $group, $group]
        );
        return ['id' => $id, 'name' => $aclName, 'created' => true];
    }
}
