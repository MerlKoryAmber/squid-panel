<?php
require_once __DIR__ . '/../app/Services/PanelTls.php';

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

$pad = str_repeat('A', 80);
$cert = "-----BEGIN CERTIFICATE-----\n{$pad}\n-----END CERTIFICATE-----\n";
try {
    PanelTls::assertPemCert($cert);
    expect(true, 'accept PEM cert');
} catch (Exception $e) {
    expect(false, 'accept PEM cert: ' . $e->getMessage());
}

try {
    PanelTls::assertPemCert("not a cert");
    expect(false, 'reject garbage cert');
} catch (Exception $e) {
    expect(true, 'reject garbage cert');
}

$key = "-----BEGIN PRIVATE KEY-----\n{$pad}\n-----END PRIVATE KEY-----\n";
try {
    PanelTls::assertPemKey($key);
    expect(true, 'accept PEM key');
} catch (Exception $e) {
    expect(false, 'accept PEM key: ' . $e->getMessage());
}

try {
    PanelTls::assertPemKey($cert);
    expect(false, 'reject cert as key');
} catch (Exception $e) {
    expect(true, 'reject cert as key');
}

exit($fail > 0 ? 1 : 0);
