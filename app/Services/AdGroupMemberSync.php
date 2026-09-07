<?php
/**
 * ADR 0010: sync AD group members → spm.db → acl.d file → proxy_auth.
 */
class AdGroupMemberSync {
    public const STAGING = 'ad-ldap-members.json';
    public const META_ID = 1;

    public static function ensureMeta() {
        $row = Database::fetch('SELECT id FROM ad_group_sync_meta WHERE id = 1');
        if (!$row) {
            Database::query(
                "INSERT INTO ad_group_sync_meta (id, last_error, updated_at) VALUES (1, '', datetime('now'))"
            );
        }
    }

    public static function meta() {
        try {
            self::ensureMeta();
            return Database::fetch('SELECT * FROM ad_group_sync_meta WHERE id = 1') ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function setMeta($ok, $error = '') {
        self::ensureMeta();
        $error = substr(trim((string)$error), 0, 2000);
        if ($ok) {
            Database::query(
                "UPDATE ad_group_sync_meta SET last_ok_at=datetime('now'), last_run_at=datetime('now'), last_error='', updated_at=datetime('now') WHERE id=1"
            );
        } else {
            Database::query(
                "UPDATE ad_group_sync_meta SET last_run_at=datetime('now'), last_error=?, updated_at=datetime('now') WHERE id=1",
                [$error]
            );
        }
    }

    /** Expand sAMAccountName to user + user@REALM. */
    public static function loginVariants($sam, $realm) {
        $sam = strtolower(trim((string)$sam));
        if ($sam === '' || !preg_match('/^[a-z0-9._-]+$/i', $sam)) {
            return [];
        }
        $out = [$sam];
        $realm = strtoupper(trim((string)$realm));
        if ($realm !== '' && preg_match('/^[A-Z0-9.-]+$/', $realm)) {
            $out[] = $sam . '@' . $realm;
        }
        return array_values(array_unique($out));
    }

    public static function importedAdAcls() {
        $rows = Database::fetchAll(
            "SELECT id, name, group_name, type, storage FROM acls
             WHERE group_name IS NOT NULL AND TRIM(group_name) != ''
               AND (type = 'proxy_auth' OR type = 'external')
             ORDER BY name"
        );
        return is_array($rows) ? $rows : [];
    }

    public static function writeMembersStaging($groupName) {
        AdLdapConfig::requireConfigured();
        $cfg = AdLdapConfig::get();
        $realm = AdGroupAcl::realm();
        $hosts = AdLdapConfig::effectiveServers();
        $payload = [
            'bind_mode' => AdLdapConfig::MODE_SIMPLE,
            'servers' => $hosts,
            'port' => (int)$cfg['port'],
            'use_ssl' => (int)$cfg['use_ssl'],
            'bind_dn' => $cfg['bind_dn'],
            'bind_password' => $cfg['bind_password'],
            'base_dn' => AdLdapConfig::baseDn($realm),
            'realm' => $realm,
            'group' => $groupName,
        ];
        $dir = (defined('SPM_STORAGE') ? SPM_STORAGE : '/opt/spm/storage') . '/tmp';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new Exception('Cannot create staging directory');
        }
        $path = $dir . '/' . self::STAGING;
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($path, $json) === false) {
            throw new Exception('Cannot write members staging');
        }
        @chmod($path, 0600);
        return self::STAGING;
    }

    public static function fetchMembersFromDirectory($groupName) {
        $groupName = AdGroupAcl::normalizeGroup($groupName);
        self::writeMembersStaging($groupName);
        $result = PrivilegedExecutor::execute('ad_ldap_group_members', [self::STAGING]);
        if (empty($result['success'])) {
            $err = trim((string)(($result['stderr'] ?? '') ?: ($result['error'] ?? '') ?: ($result['stdout'] ?? 'member list failed')));
            throw new Exception($err !== '' ? $err : 'member list failed');
        }
        $out = (string)($result['stdout'] ?? '');
        $lines = preg_split('/\R/', $out) ?: [];
        $names = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '__') === 0) {
                continue;
            }
            if (preg_match('/^[A-Za-z0-9._-]+$/', $line)) {
                $names[] = $line;
            }
        }
        return array_values(array_unique($names));
    }

    /**
     * Replace members for one ACL from LDAP sam list. Returns whether file content changed.
     */
    public static function applyMembersToAcl(array $acl, array $samNames) {
        $aclId = (int)$acl['id'];
        $aclName = (string)$acl['name'];
        $realm = AdGroupAcl::realm();
        $logins = [];
        foreach ($samNames as $sam) {
            foreach (self::loginVariants($sam, $realm) as $u) {
                $logins[$u] = true;
            }
        }
        $sorted = array_keys($logins);
        sort($sorted, SORT_STRING);

        Database::query('DELETE FROM ad_group_members WHERE acl_id = ?', [$aclId]);
        foreach ($sorted as $u) {
            Database::query(
                "INSERT INTO ad_group_members (acl_id, username, created_at) VALUES (?, ?, datetime('now'))",
                [$aclId, $u]
            );
        }

        $prev = AclListFile::readWorkFile($aclName);
        sort($prev, SORT_STRING);
        $changed = ($prev !== $sorted);

        AclListFile::writeWorkFile($aclName, $sorted);
        $inst = AclListFile::installLive($aclName);
        if (empty($inst['success'])) {
            $err = trim((string)(($inst['stderr'] ?? '') ?: ($inst['error'] ?? '') ?: 'acl_file_install failed'));
            throw new Exception($err);
        }
        return $changed;
    }

    public static function memberCount($aclId) {
        $row = Database::fetch(
            'SELECT COUNT(*) AS c FROM ad_group_members WHERE acl_id = ?',
            [(int)$aclId]
        );
        return (int)($row['c'] ?? 0);
    }

    /**
     * Sync all imported AD groups. On per-group LDAP failure: keep previous members.
     * @return array{ok:bool,synced:int,changed:int,errors:string[],message:string}
     */
    public static function syncAll() {
        AdGroupAcl::migrateLegacyExternalToProxyAuth();
        $acls = self::importedAdAcls();
        $errors = [];
        $synced = 0;
        $changed = 0;
        foreach ($acls as $acl) {
            if (($acl['type'] ?? '') !== 'proxy_auth') {
                continue;
            }
            $g = trim((string)($acl['group_name'] ?? ''));
            if ($g === '') {
                continue;
            }
            try {
                $sams = self::fetchMembersFromDirectory($g);
                if (self::applyMembersToAcl($acl, $sams)) {
                    $changed++;
                }
                $synced++;
            } catch (Throwable $e) {
                $errors[] = $g . ': ' . $e->getMessage();
            }
        }
        $ok = empty($errors);
        $msg = 'Synced ' . $synced . ' group(s)';
        if ($changed) {
            $msg .= ', ' . $changed . ' file(s) changed';
        }
        if ($errors) {
            $msg .= '. Errors: ' . implode('; ', $errors);
            self::setMeta(false, implode('; ', $errors));
        } else {
            self::setMeta(true, '');
        }
        return [
            'ok' => $ok,
            'synced' => $synced,
            'changed' => $changed,
            'errors' => $errors,
            'message' => $msg,
        ];
    }
}
