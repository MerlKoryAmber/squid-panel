<?php
class SquidPolicyApply {
    public const PARSE_NAME = 'squid.conf.parse';

    public static function stageFromDatabase() {
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
        PanelNet::writeTmp(self::PARSE_NAME, $body);
        return $body;
    }
}
