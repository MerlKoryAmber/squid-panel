<?php
/**
 * Negotiate helper queue from cache.log (Squid 5 WARNING/FATAL).
 * Event log beats a schedule: a 12h squidclient poll can miss the spike.
 */
class NegotiateHelperStats {
    private const WINDOW = 86400;
    private const TAIL_BYTES = 1048576;

    public static function dashboard() {
        $auth = Database::fetch("SELECT children, children_extra FROM auth_config WHERE scheme = 'negotiate' LIMIT 1") ?: [];
        $extra = (string)($auth['children_extra'] ?? '');
        $startup = 0;
        $idle = 10;
        if (preg_match('/\bstartup=(\d+)/', $extra, $m)) {
            $startup = (int)$m[1];
        }
        if (preg_match('/\bidle=(\d+)/', $extra, $m)) {
            $idle = (int)$m[1];
        }
        $log = defined('SQUID_CACHE_LOG') ? SQUID_CACHE_LOG : '/var/log/squid/cache.log';
        $scan = self::scanWindow($log, time() - self::WINDOW);
        $scan['children'] = isset($auth['children']) ? (int)$auth['children'] : null;
        $scan['startup'] = $startup;
        $scan['idle'] = $idle;
        $scan['configured'] = $auth !== [];
        return $scan;
    }

    public static function scanWindow($logPath, $sinceTs) {
        $readable = is_file($logPath) && is_readable($logPath);
        $text = $readable ? self::tailBytes($logPath, self::TAIL_BYTES) : '';
        if ($readable && is_readable($logPath . '.1')) {
            $text = self::tailBytes($logPath . '.1', (int)(self::TAIL_BYTES / 2)) . "\n" . $text;
        }
        $out = self::parseChunk($text, $sinceTs);
        $out['readable'] = $readable;
        return $out;
    }

    public static function parseChunk($text, $sinceTs) {
        $busy = 0;
        $fatal = 0;
        $maxQueued = 0;
        $lastBusyLabel = '';
        $busyN = null;
        $busyD = null;
        $expectQueued = false;

        foreach (preg_split("/\r\n|\n|\r/", (string)$text) as $line) {
            if ($line === '') {
                continue;
            }
            $ts = self::lineTime($line);
            if ($ts !== null && $ts < $sinceTs) {
                $expectQueued = false;
                continue;
            }
            if (preg_match('/All\s+(\d+)\/(\d+)\s+negotiateauthenticator processes are busy/i', $line, $m)) {
                $busy++;
                $busyN = (int)$m[1];
                $busyD = (int)$m[2];
                $expectQueued = true;
                if ($ts !== null) {
                    $lastBusyLabel = date('Y-m-d H:i:s', $ts);
                }
                continue;
            }
            if ($expectQueued && preg_match('/(\d+)\s+pending requests queued/i', $line, $m)) {
                $q = (int)$m[1];
                if ($q > $maxQueued) {
                    $maxQueued = $q;
                }
                $expectQueued = false;
                continue;
            }
            $expectQueued = false;
            if (stripos($line, 'Too many queued negotiateauthenticator') !== false) {
                $fatal++;
                if ($ts !== null) {
                    $lastBusyLabel = date('Y-m-d H:i:s', $ts);
                }
            }
        }

        $ok = $busy === 0 && $fatal === 0;
        return [
            'ok' => $ok,
            'readable' => true,
            'busy_count' => $busy,
            'fatal_count' => $fatal,
            'max_queued' => $maxQueued,
            'last_busy' => $lastBusyLabel,
            'busy_ratio' => ($busyN !== null && $busyD !== null) ? ($busyN . '/' . $busyD) : '',
            'window_h' => 24,
        ];
    }

    private static function lineTime($line) {
        if (!preg_match('#^(\d{4}/\d{2}/\d{2}\s+\d{2}:\d{2}:\d{2})#', $line, $m)) {
            return null;
        }
        $ts = strtotime($m[1]);
        return $ts !== false ? $ts : null;
    }

    private static function tailBytes($path, $maxBytes) {
        if (!is_readable($path) || !is_file($path)) {
            return '';
        }
        $size = filesize($path);
        if ($size === false || $size <= 0) {
            return '';
        }
        $fh = fopen($path, 'rb');
        if (!$fh) {
            return '';
        }
        if ($size > $maxBytes) {
            fseek($fh, -$maxBytes, SEEK_END);
        }
        $data = stream_get_contents($fh);
        fclose($fh);
        return is_string($data) ? $data : '';
    }
}
