<?php
/**
 * Large ACL value lists live in a text file Squid can include,
 * not as thousands of tokens in squid.conf or JSON in SQLite.
 */
class AclListFile {
    public const FILE_TYPES = [
        'dstdomain' => true,
        'srcdomain' => true,
        'dst' => true,
        'src' => true,
    ];
    public const AUTO_FILE_MIN = 80;
    public const MAX_BYTES = 5242880;
    public const MAX_LINES = 50000;

    public static function workDir() {
        $dir = (defined('SPM_STORAGE') ? SPM_STORAGE : '/opt/spm/storage') . '/acl';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return $dir;
    }

    public static function liveDir() {
        return defined('ACL_LIST_LIVE_DIR') ? ACL_LIST_LIVE_DIR : '/etc/squid/acl.d';
    }

    public static function fileName($aclName) {
        $base = preg_replace('/[^A-Za-z0-9._-]/', '', (string)$aclName);
        if ($base === '' || $base === '.' || $base === '..') {
            throw new Exception('Invalid ACL name for file list');
        }
        return $base . '.txt';
    }

    public static function workPath($aclName) {
        return self::workDir() . '/' . self::fileName($aclName);
    }

    public static function livePath($aclName) {
        return self::liveDir() . '/' . self::fileName($aclName);
    }

    public static function squidRef($aclName) {
        return '"' . self::livePath($aclName) . '"';
    }

    public static function isFileType($type) {
        return isset(self::FILE_TYPES[$type]);
    }

    public static function parseLines($raw) {
        $lines = preg_split('/\R/', (string)$raw);
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            $out[] = $line;
        }
        return array_values(array_unique($out));
    }

    public static function writeWorkFile($aclName, array $values) {
        if (count($values) > self::MAX_LINES) {
            throw new Exception('ACL list exceeds ' . self::MAX_LINES . ' entries');
        }
        $body = implode("\n", $values);
        if ($body !== '') {
            $body .= "\n";
        }
        if (strlen($body) > self::MAX_BYTES) {
            throw new Exception('ACL list file is too large');
        }
        $path = self::workPath($aclName);
        $tmp = $path . '.tmp';
        if (file_put_contents($tmp, $body) === false) {
            throw new Exception('Cannot write ACL list working copy');
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new Exception('Cannot replace ACL list working copy');
        }
        @chmod($path, 0640);
        return $path;
    }

    public static function readWorkFile($aclName) {
        $path = self::workPath($aclName);
        if (!is_readable($path)) {
            $live = self::livePath($aclName);
            if (is_readable($live)) {
                $path = $live;
            } else {
                return [];
            }
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return [];
        }
        return self::parseLines($raw);
    }

    public static function countWorkFile($aclName) {
        return count(self::readWorkFile($aclName));
    }

    public static function installLive($aclName) {
        return PrivilegedExecutor::execute('acl_file_install', [self::fileName($aclName)]);
    }

    public static function looksLikeFileRef($value) {
        $value = trim((string)$value, " \t\"'");
        return (bool)preg_match('#^/.+\.txt$#', $value);
    }
}
