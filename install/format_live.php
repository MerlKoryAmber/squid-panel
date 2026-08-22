<?php
/**
 * After import: write panel-shaped squid.conf. Parse first; live unchanged on fail.
 * Run as root from install.sh.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/squid.php';
require_once __DIR__ . '/../app/Core/Database.php';

foreach (glob(__DIR__ . '/../app/Services/*.php') as $file) {
    require_once $file;
}

Database::init();

$live = defined('SQUID_CONF') ? SQUID_CONF : '/etc/squid/squid.conf';
$parse = defined('SQUID_PARSE_FILE') ? SQUID_PARSE_FILE : '/opt/spm/storage/tmp/squid.conf.parse';
$aclLive = AclListFile::liveDir();
if (!is_dir($aclLive)) {
    mkdir($aclLive, 0755, true);
}

foreach (Database::fetchAll("SELECT name, storage FROM acls") as $acl) {
    if (($acl['storage'] ?? '') !== 'file') {
        continue;
    }
    $src = AclListFile::workPath($acl['name']);
    $dst = AclListFile::livePath($acl['name']);
    if (!is_readable($src)) {
        fwrite(STDERR, "ACL file missing: {$src}\n");
        exit(1);
    }
    if (!copy($src, $dst)) {
        fwrite(STDERR, "Cannot copy ACL list to {$dst}\n");
        exit(1);
    }
    @chmod($dst, 0644);
}

$builder = (new SquidConfigBuilder())->loadFromDatabase();
$body = $builder->generate();
if (strpos($body, 'http_access deny all') === false || strpos($body, 'http_port ') === false) {
    fwrite(STDERR, "Generated squid.conf is missing http_port or deny all\n");
    exit(1);
}

$dir = dirname($parse);
if (!is_dir($dir)) {
    mkdir($dir, 0700, true);
}
if (file_put_contents($parse, $body) === false) {
    fwrite(STDERR, "Cannot write {$parse}\n");
    exit(1);
}
@chmod($parse, 0600);
$tmpOwner = @fileowner(SPM_STORAGE);
$tmpGroup = @filegroup(SPM_STORAGE);
if ($tmpOwner !== false && function_exists('posix_chown')) {
    @posix_chown($parse, $tmpOwner);
}
if ($tmpGroup !== false && function_exists('posix_chgrp')) {
    @posix_chgrp($parse, $tmpGroup);
}

$out = [];
$rc = 0;
exec('/usr/sbin/squid -f ' . escapeshellarg($parse) . ' -k parse 2>&1', $out, $rc);
if ($rc !== 0) {
    fwrite(STDERR, "squid -k parse failed:\n" . implode("\n", $out) . "\n");
    exit(1);
}

if (!copy($parse, $live)) {
    fwrite(STDERR, "Cannot replace {$live}\n");
    exit(1);
}
@chmod($live, 0644);

$re = [];
$rr = 0;
exec('/usr/sbin/squid -k reconfigure 2>&1', $re, $rr);
if ($rr !== 0) {
    fwrite(STDERR, "squid -k reconfigure failed:\n" . implode("\n", $re) . "\n");
    exit(1);
}

echo "Formatted live squid.conf (" . strlen($body) . " bytes). Parse and reconfigure OK.\n";
