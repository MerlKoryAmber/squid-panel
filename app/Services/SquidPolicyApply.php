<?php
class SquidPolicyApply {
    public const FILES = [
        'spm-acl.conf',
        'spm-peers.conf',
        'spm-http_access.conf',
    ];

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
        $parts = [
            'spm-acl.conf' => $builder->fragmentAcl(),
            'spm-peers.conf' => $builder->fragmentPeers(),
            'spm-http_access.conf' => $builder->fragmentHttpAccess(),
        ];
        foreach ($parts as $name => $body) {
            if (strlen($body) > 2 * 1024 * 1024) {
                throw new Exception($name . ' is too large to apply');
            }
            PanelNet::writeTmp($name, $body);
        }
        return $parts;
    }
}
