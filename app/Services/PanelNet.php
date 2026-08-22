<?php
class PanelNet {
    public static function clientIp() {
        $raw = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if (strpos($raw, ':') !== false && preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $raw, $m)) {
            $raw = $m[1];
        }
        return $raw;
    }

    public static function parseAllowList($text) {
        $out = [];
        foreach (preg_split("/\r\n|\n|\r/", (string)$text) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (!self::validAllowToken($line)) {
                throw new Exception('Invalid allow IP/CIDR: ' . $line);
            }
            $out[$line] = true;
        }
        return array_keys($out);
    }

    public static function validAllowToken($token) {
        $token = trim((string)$token);
        if ($token === '127.0.0.1' || $token === '::1') {
            return true;
        }
        if (preg_match('/^(\d{1,3}\.){3}\d{1,3}(\/\d{1,2})?$/', $token)) {
            $parts = explode('/', $token, 2);
            if (filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                return false;
            }
            if (isset($parts[1])) {
                $p = (int)$parts[1];
                return $p >= 8 && $p <= 32;
            }
            return true;
        }
        if (filter_var($token, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return true;
        }
        if (preg_match('/^([0-9a-fA-F:]+)\/(\d{1,3})$/', $token, $m)) {
            if (filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                return false;
            }
            $p = (int)$m[2];
            return $p >= 32 && $p <= 128;
        }
        return false;
    }

    public static function nginxAllowFile($ips) {
        $lines = ['# SPM panel IP allowlist. Empty (no deny) = all IPs.'];
        if (empty($ips)) {
            return implode("\n", $lines) . "\n";
        }
        $haveLoop = false;
        foreach ($ips as $ip) {
            if ($ip === '127.0.0.1' || $ip === '::1') {
                $haveLoop = true;
            }
            $lines[] = 'allow ' . $ip . ';';
        }
        if (!$haveLoop) {
            array_splice($lines, 1, 0, ['allow 127.0.0.1;', 'allow ::1;']);
        }
        $lines[] = 'deny all;';
        return implode("\n", $lines) . "\n";
    }

    public static function listenFile($httpPortRaw, $hostname) {
        $lines = ['# SPM managed listen/hostname. Included from squid.conf.'];
        $ports = self::parseHttpPortLines($httpPortRaw);
        if (empty($ports)) {
            throw new Exception('http_port is required');
        }
        foreach ($ports as $p) {
            $lines[] = 'http_port ' . $p;
        }
        $hostname = trim((string)$hostname);
        if ($hostname !== '') {
            if (!preg_match('/^[A-Za-z0-9._-]+$/', $hostname)) {
                throw new Exception('Invalid visible_hostname');
            }
            $lines[] = 'visible_hostname ' . $hostname;
        }
        return implode("\n", $lines) . "\n";
    }

    public static function parseHttpPortLines($raw) {
        $out = [];
        foreach (preg_split("/\r\n|\n|\r/", (string)$raw) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (stripos($line, 'http_port ') === 0) {
                $line = trim(substr($line, 9));
            }
            if (!self::validHttpPortLine($line)) {
                throw new Exception('Invalid http_port: ' . $line);
            }
            $out[] = $line;
        }
        return $out;
    }

    public static function validHttpPortLine($line) {
        $line = trim((string)$line);
        if ($line === '' || strlen($line) > 200) {
            return false;
        }
        if (strpbrk($line, ";|&<>`\n\r") !== false) {
            return false;
        }
        return (bool)preg_match(
            '/^(?:(?:\d{1,3}\.){3}\d{1,3}:)?\d{2,5}(?:\s+[A-Za-z0-9._:=-]+)*$/',
            $line
        );
    }

    public static function parseRequestHeaderAccessLines($raw) {
        $out = [];
        foreach (preg_split("/\r\n|\n|\r/", (string)$raw) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (stripos($line, 'request_header_access ') === 0) {
                $line = trim(substr($line, strlen('request_header_access ')));
            }
            if (!self::validRequestHeaderAccessLine($line)) {
                throw new Exception('Invalid request_header_access: ' . $line);
            }
            $out[] = $line;
        }
        return $out;
    }

    public static function validRequestHeaderAccessLine($line) {
        $line = trim((string)$line);
        if ($line === '' || strlen($line) > 300) {
            return false;
        }
        if (strpbrk($line, ";|&<>`\n\r") !== false) {
            return false;
        }
        return (bool)preg_match(
            '/^[A-Za-z0-9_:-]+ (allow|deny)(?:\s+[A-Za-z0-9._:!*-]+)*$/',
            $line
        );
    }

    public static function writeTmp($name, $body) {
        $dir = (defined('SPM_STORAGE') ? SPM_STORAGE : '/opt/spm/storage') . '/tmp';
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new Exception('Cannot create staging directory');
        }
        $path = $dir . '/' . $name;
        if (is_file($path) && !is_writable($path) && !@unlink($path)) {
            throw new Exception('Cannot write ' . $name . ' (not writable)');
        }
        if (file_put_contents($path, $body) === false) {
            throw new Exception('Cannot write ' . $name);
        }
        @chmod($path, 0600);
        return $path;
    }
}
