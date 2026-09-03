<?php
require_once __DIR__ . '/../app/Services/AdGroupAcl.php';

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

$hosts = AdGroupAcl::parseLdapServers("hdc-01.hci.interros.ru\nhdc-02.hci.interros.ru");
expect($hosts === ['hdc-01.hci.interros.ru', 'hdc-02.hci.interros.ru'], 'parse two lines');

$hosts2 = AdGroupAcl::parseLdapServers('hdc-01.hci.interros.ru, hdc-02.hci.interros.ru');
expect($hosts2 === $hosts, 'parse comma');

$flag = AdGroupAcl::ldapServersFlag($hosts, 'HCI.INTERROS.RU');
expect(
    $flag === '-S hdc-01.hci.interros.ru@HCI.INTERROS.RU:hdc-02.hci.interros.ru@HCI.INTERROS.RU',
    'flag with realm'
);

$opts = AdGroupAcl::withLdapServerList('-a -g WWW_DIT_Allow -D HCI.INTERROS.RU', $hosts, 'HCI.INTERROS.RU');
expect(strpos($opts, '-S hdc-01.hci.interros.ru@HCI.INTERROS.RU:hdc-02') !== false, 'inject -S');
expect(substr_count($opts, '-S ') === 1, 'single -S');

$opts2 = AdGroupAcl::withLdapServerList($opts . ' -S old.example@X', $hosts, 'HCI.INTERROS.RU');
expect(strpos($opts2, 'old.example') === false, 'strip old -S');
expect(substr_count($opts2, '-S ') === 1, 'still one -S');

$cleared = AdGroupAcl::withLdapServerList($opts2, [], 'HCI.INTERROS.RU');
expect(strpos($cleared, '-S ') === false, 'empty hosts removes -S');

try {
    AdGroupAcl::parseLdapServers('bad host');
    expect(false, 'reject space hostname');
} catch (Exception $e) {
    expect(true, 'reject space hostname');
}

require_once __DIR__ . '/../app/Services/AclListFile.php';
require_once __DIR__ . '/../app/Services/PanelNet.php';
require_once __DIR__ . '/../app/Services/SquidConfigBuilder.php';

$b = (new SquidConfigBuilder())->loadFromArray([
    'auth' => [
        [
            'scheme' => 'negotiate',
            'program' => '/usr/lib64/squid/negotiate_kerberos_auth',
            'children' => 20,
            'realm' => 'HCI.INTERROS.RU',
            'ldap_servers' => "hdc-01.hci.interros.ru\nhdc-02.hci.interros.ru",
        ],
    ],
    'ext_acl' => [
        [
            'name' => 'www_DIT_Allow',
            'format' => '%LOGIN',
            'ttl' => 300,
            'negative_ttl' => 60,
            'children' => 10,
            'program' => '/usr/lib64/squid/ext_kerberos_ldap_group_acl',
            'options' => '-a -g WWW_DIT_Allow -D HCI.INTERROS.RU',
        ],
    ],
    'acls' => [],
    'http_access' => [],
    'peers' => [],
    'peer_access' => [],
    'routing' => [],
    'globals' => ['http_port' => '3128'],
]);

$ext = $b->fragmentExternalAcl();
expect(strpos($ext, '-S hdc-01.hci.interros.ru@HCI.INTERROS.RU:hdc-02.hci.interros.ru@HCI.INTERROS.RU') !== false, 'builder emits -S');

if ($fail > 0) {
    fwrite(STDERR, "$fail failed\n");
    exit(1);
}
echo "All ldap_servers checks passed.\n";
