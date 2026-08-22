<?php
/**
 * Generated squid.conf order: auth_param then external_acl_type then acl … external.
 */
require_once __DIR__ . '/../app/Services/AclListFile.php';
require_once __DIR__ . '/../app/Services/PanelNet.php';
require_once __DIR__ . '/../app/Services/SquidConfigBuilder.php';

$fail = 0;
function expect($ok, $msg) {
    global $fail;
    if (!$ok) {
        fwrite(STDERR, "FAIL $msg\n");
        $fail++;
    } else {
        echo "ok $msg\n";
    }
}

$b = (new SquidConfigBuilder())->loadFromArray([
    'auth' => [[
        'scheme' => 'negotiate',
        'program' => '/usr/lib64/squid/negotiate_kerberos_auth -k /etc/krb5.keytab',
        'children' => 5,
        'children_extra' => '',
        'keep_alive' => 'on',
    ]],
    'ext_acl' => [[
        'name' => 'www_DIT_Allow',
        'format' => '%LOGIN',
        'ttl' => 3600,
        'negative_ttl' => 60,
        'children' => 10,
        'program' => '/usr/lib64/squid/ext_kerberos_ldap_group_acl',
        'options' => '',
    ]],
    'acls' => [[
        'name' => 'DIT_AD',
        'type' => 'external',
        'storage' => 'inline',
        'entries' => json_encode(['www_DIT_Allow']),
    ]],
    'http_access' => [],
    'peers' => [],
    'globals' => ['http_port' => '3128', 'extra_conf' => 'cache_mem 0'],
]);
$out = $b->generate();
$auth = strpos($out, 'auth_param negotiate');
$ext = strpos($out, 'external_acl_type www_DIT_Allow');
$acl = strpos($out, 'acl DIT_AD external');
expect($auth !== false && $ext !== false && $acl !== false, 'all three present');
expect($ext > $auth, 'external_acl after auth_param');
expect($acl > $ext, 'acl after external_acl_type');

if ($fail > 0) {
    exit(1);
}
echo "PASS\n";
