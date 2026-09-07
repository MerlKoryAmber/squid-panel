<?php
class SquidPolicyApply {
    public const PARSE_NAME = 'squid.conf.parse';

    public static function stageFromDatabase() {
        // ADR 0010: convert legacy kg_* helpers before generating conf, else Apply
        // would drop helpers while ACLs still reference them.
        AdGroupAcl::migrateLegacyExternalToProxyAuth();
        $builder = (new SquidConfigBuilder())->loadFromDatabase();
        foreach (Database::fetchAll("SELECT name, storage FROM acls") as $acl) {
            if (($acl['storage'] ?? '') === 'file') {
                $copy = AclListFile::installLive($acl['name']);
                if (empty($copy['success'])) {
                    $err = trim((string)(($copy['stderr'] ?? '') ?: ($copy['error'] ?? '') ?: ($copy['stdout'] ?? 'acl file install failed')));
                    throw new Exception('ACL file ' . $acl['name'] . ': ' . $err);
                }
            }
        }
        $body = $builder->generate();
        if (strlen($body) > 2 * 1024 * 1024) {
            throw new Exception('generated squid.conf is too large to apply');
        }
        if (strpos($body, 'http_access deny all') === false) {
            throw new Exception('generated squid.conf has no http_access deny all');
        }
        if (strpos($body, 'http_port ') === false) {
            throw new Exception('generated squid.conf has no http_port');
        }
        if (strpos($body, 'ext_kerberos_ldap_group_acl') !== false
            || strpos($body, 'kerberos_ldap_group') !== false) {
            throw new Exception('generated squid.conf still contains LDAP group helper (ADR 0010)');
        }
        PanelNet::writeTmp(self::PARSE_NAME, $body);
        return $body;
    }
}
