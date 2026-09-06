<?php
/**
 * Stage URL for headless domain discovery (ADR 0009). Not Squid.
 */
class DomainDiscover {
    public const STAGING = 'discover-job.json';
    public const MAX_URL = 2048;

    /** Multi-label public suffixes (not exhaustive; covers common cases). */
    private static $multiTld = [
        'co.uk' => true, 'org.uk' => true, 'ac.uk' => true, 'gov.uk' => true,
        'co.jp' => true, 'or.jp' => true, 'ne.jp' => true,
        'com.au' => true, 'net.au' => true, 'org.au' => true,
        'com.br' => true, 'com.tr' => true, 'co.za' => true,
        'com.cn' => true, 'com.hk' => true, 'com.sg' => true,
        'co.il' => true, 'com.ua' => true, 'co.kr' => true,
    ];

    /**
     * Reduce FQDN to registrable / 2nd-level style domain (example.com).
     * Subdomains discarded. Returns '' if not usable.
     */
    public static function toSecondLevel($host) {
        $host = strtolower(trim((string)$host));
        $host = rtrim($host, '.');
        if ($host === '' || !preg_match('/^[a-z0-9.-]+$/', $host) || strpos($host, '..') !== false) {
            return '';
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return '';
        }
        $parts = explode('.', $host);
        $n = count($parts);
        if ($n < 2) {
            return '';
        }
        $last2 = $parts[$n - 2] . '.' . $parts[$n - 1];
        if ($n >= 3 && isset(self::$multiTld[$last2])) {
            return $parts[$n - 3] . '.' . $last2;
        }
        return $last2;
    }

    /** Unique sorted second-level domains from a hostname list. */
    public static function uniqueSecondLevel(array $hosts) {
        $out = [];
        foreach ($hosts as $h) {
            $d = self::toSecondLevel($h);
            if ($d !== '') {
                $out[$d] = true;
            }
        }
        $keys = array_keys($out);
        sort($keys);
        return $keys;
    }

    /** Path to short curated ads/analytics denylist (2nd-level domains). */
    public static function adDenylistPath() {
        return (defined('SPM_ROOT') ? SPM_ROOT : dirname(__DIR__, 2)) . '/app/Data/discover_ad_denylist.txt';
    }

    /** @return array<string,true> map of denylisted 2nd-level domains */
    public static function adDenylistMap() {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = [];
        $path = self::adDenylistPath();
        if (!is_readable($path)) {
            return $map;
        }
        $fh = fopen($path, 'rb');
        if (!$fh) {
            return $map;
        }
        while (($line = fgets($fh)) !== false) {
            $line = strtolower(trim($line));
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $d = self::toSecondLevel($line);
            if ($d !== '') {
                $map[$d] = true;
            }
        }
        fclose($fh);
        return $map;
    }

    /**
     * Drop domains present in the ad/analytics denylist.
     * @return array{kept:string[],removed:int}
     */
    public static function filterAdDomains(array $domains) {
        $deny = self::adDenylistMap();
        if (empty($deny)) {
            return ['kept' => array_values($domains), 'removed' => 0];
        }
        $kept = [];
        $removed = 0;
        foreach ($domains as $d) {
            $d = strtolower(trim((string)$d));
            if ($d === '') {
                continue;
            }
            if (isset($deny[$d])) {
                $removed++;
                continue;
            }
            $kept[] = $d;
        }
        return ['kept' => $kept, 'removed' => $removed];
    }

    public static function normalizeUrl($raw) {
        $raw = trim((string)$raw);
        if ($raw === '' || strlen($raw) > self::MAX_URL || strpos($raw, "\0") !== false) {
            throw new Exception('Invalid URL');
        }
        if (preg_match('#^([a-zA-Z][a-zA-Z0-9+.-]*):#', $raw, $m)) {
            $sch = strtolower($m[1]);
            if ($sch !== 'http' && $sch !== 'https') {
                throw new Exception('Only http/https allowed');
            }
        } else {
            $raw = 'https://' . $raw;
        }
        $p = parse_url($raw);
        if (!is_array($p) || empty($p['scheme']) || empty($p['host'])) {
            throw new Exception('URL must include host');
        }
        $scheme = strtolower((string)$p['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new Exception('Only http/https allowed');
        }
        $host = (string)$p['host'];
        if (!preg_match('/^[A-Za-z0-9.-]+$/', $host) || strlen($host) > 253) {
            throw new Exception('Invalid hostname');
        }
        $path = isset($p['path']) && $p['path'] !== '' ? (string)$p['path'] : '/';
        $query = isset($p['query']) ? ('?' . $p['query']) : '';
        $port = isset($p['port']) ? (':' . (int)$p['port']) : '';
        return $scheme . '://' . $host . $port . $path . $query;
    }

    public static function writeStaging($url) {
        $url = self::normalizeUrl($url);
        $dir = (defined('SPM_STORAGE') ? SPM_STORAGE : '/opt/spm/storage') . '/tmp';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new Exception('Cannot create staging directory');
        }
        $path = $dir . '/' . self::STAGING;
        $json = json_encode(['url' => $url, 'timeout_sec' => 45], JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($path, $json) === false) {
            throw new Exception('Cannot write discover staging');
        }
        @chmod($path, 0600);
        return self::STAGING;
    }
}
