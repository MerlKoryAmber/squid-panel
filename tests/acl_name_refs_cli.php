<?php
require_once __DIR__ . '/../app/Services/AclNameRefs.php';

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

expect(AclNameRefs::rewriteBare('office', 'office', 'lan') === 'lan', 'bare rename');
expect(AclNameRefs::rewriteBare('!office', 'office', 'lan') === '!lan', 'negated rename');
expect(AclNameRefs::rewriteBare('office_sites', 'office', 'lan') === 'office_sites', 'no prefix match');
expect(AclNameRefs::rewriteSpaceList('office SSL_ports', 'office', 'lan') === 'lan SSL_ports', 'space list');
expect(AclNameRefs::rewriteSpaceList('!office banks', 'office', 'lan') === '!lan banks', 'space negated');
$j = AclNameRefs::rewriteJsonList(json_encode(['office', '!office', 'SSL_ports']), 'office', 'lan');
expect($j === json_encode(['lan', '!lan', 'SSL_ports']), 'json list');
expect(AclNameRefs::rewriteJsonList('not-json', 'a', 'b') === 'not-json', 'leave non-json');

if ($fail > 0) {
    fwrite(STDERR, "FAILED $fail\n");
    exit(1);
}
echo "ALL_OK\n";
