<?php
require_once __DIR__ . '/../app/Services/AdGroupMemberSync.php';
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

$v = AdGroupMemberSync::loginVariants('Alice', 'HCI.EXAMPLE.COM');
expect($v === ['alice', 'alice@HCI.EXAMPLE.COM'], 'both login forms: ' . json_encode($v));

$v2 = AdGroupMemberSync::loginVariants('bob.smith', 'EXAMPLE.COM');
expect(in_array('bob.smith', $v2, true) && in_array('bob.smith@EXAMPLE.COM', $v2, true), 'sam with dot');

$v3 = AdGroupMemberSync::loginVariants('bad name', 'X');
expect($v3 === [], 'reject space in sam');

exit($fail > 0 ? 1 : 0);
