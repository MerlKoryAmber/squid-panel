<?php
require_once __DIR__ . '/../app/Services/DomainDiscover.php';

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

$u = DomainDiscover::normalizeUrl('example.com');
expect($u === 'https://example.com/', 'bare host → https');

$u2 = DomainDiscover::normalizeUrl('http://foo.example/path?q=1');
expect($u2 === 'http://foo.example/path?q=1', 'keep http path query');

try {
    DomainDiscover::normalizeUrl('ftp://x');
    expect(false, 'reject ftp');
} catch (Exception $e) {
    expect(true, 'reject ftp');
}

try {
    DomainDiscover::normalizeUrl('https://bad host');
    expect(false, 'reject space host');
} catch (Exception $e) {
    expect(true, 'reject space host');
}

expect(DomainDiscover::toSecondLevel('cdn.static.example.com') === 'example.com', 'strip to 2nd level');
expect(DomainDiscover::toSecondLevel('example.com') === 'example.com', 'keep 2nd level');
expect(DomainDiscover::toSecondLevel('www.bbc.co.uk') === 'bbc.co.uk', 'multi TLD');
expect(DomainDiscover::toSecondLevel('1.2.3.4') === '', 'skip IP');
$u3 = DomainDiscover::uniqueSecondLevel(['a.example.com', 'b.example.com', 'other.org']);
expect($u3 === ['example.com', 'other.org'], 'unique second-level list');

$map = DomainDiscover::adDenylistMap();
expect(count($map) > 20 && count($map) < 500, 'short denylist loaded (' . count($map) . ')');
expect(!empty($map['doubleclick.net']), 'seed has doubleclick.net');
expect(empty($map['101com.com']), 'not full Peter Lowe seed');
$filt = DomainDiscover::filterAdDomains(['spm-lab-unique.example', 'doubleclick.net', 'google-analytics.com']);
expect($filt['kept'] === ['spm-lab-unique.example'], 'filter keeps only lab unique');
expect($filt['removed'] === 2, 'removed 2 ad domains');

exit($fail > 0 ? 1 : 0);
