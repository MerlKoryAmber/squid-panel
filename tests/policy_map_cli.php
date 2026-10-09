<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Services/PolicyMapBuilder.php';

Database::init();

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

$map = PolicyMapBuilder::build();
expect(isset($map['http_access'], $map['peers'], $map['peer_access'], $map['routing'], $map['acl_index']), 'keys');
expect(is_array($map['http_access']), 'http_access array');
expect(is_array($map['acl_index']), 'acl_index array');
expect(isset($map['svg_access'], $map['svg_cascade']), 'svg keys');
expect(strpos((string)$map['svg_access'], '<svg') !== false, 'svg_access is svg');
expect(strpos((string)$map['svg_cascade'], '<svg') !== false, 'svg_cascade is svg');

exit($fail > 0 ? 1 : 0);
