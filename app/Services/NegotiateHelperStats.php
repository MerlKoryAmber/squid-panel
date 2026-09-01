<?php
/**
 * Negotiate helper queue from cache.log (Squid 5 WARNING/FATAL).
 * Hourly cron stores per-hour peaks in negotiate_helper_hourly.
 */
class NegotiateHelperStats {
    private const WINDOW = 86400;
    private const TAIL_BYTES = 1048576;
    private const RETAIN_DAYS = 8;

    public static function dashboard() {
        self::maybeRecordHourly();
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
        $history = self::aggregateHistory(24 * 7);
        if ($history['sample_count'] > 0) {
            $scan['ok'] = $history['busy_events'] === 0 && $history['fatal_events'] === 0;
            $scan['busy_count'] = $history['busy_events'];
            $scan['fatal_count'] = $history['fatal_events'];
            $scan['max_queued'] = $history['max_queued'];
            $scan['window_h'] = $history['window_h'];
            $scan['from_hourly'] = true;
        } else {
            $scan['from_hourly'] = false;
        }
        $scan['children'] = isset($auth['children']) ? (int)$auth['children'] : null;
        $scan['startup'] = $startup;
        $scan['idle'] = $idle;
        $scan['configured'] = $auth !== [];
        $scan['last_sample_hour'] = $history['last_hour'] ?? '';
        $scan['sample_count'] = $history['sample_count'];
        $scan['peak_queued_7d'] = $history['peak_queued_7d'];
        return $scan;
    }

    /** Previous calendar hour → SQLite (cron, top of each hour). */
    public static function recordHourlySample($now = null) {
        $auth = Database::fetch("SELECT 1 AS ok FROM auth_config WHERE scheme = 'negotiate' LIMIT 1");
        if (!$auth) {
            return ['skipped' => true, 'reason' => 'no negotiate'];
        }
        $now = $now ?? time();
        $hourStart = strtotime(date('Y-m-d H:00:00', $now - 3600));
        $hourEnd = $hourStart + 3600;
        $hourLabel = date('Y-m-d H:00:00', $hourStart);

        $log = defined('SQUID_CACHE_LOG') ? SQUID_CACHE_LOG : '/var/log/squid/cache.log';
        $text = self::loadLogTail($log);
        $parsed = self::parseChunkWindow($text, $hourStart, $hourEnd);

        Database::query(
            "INSERT INTO negotiate_helper_hourly (hour_start, busy_events, max_queued, fatal_events, created_at)
             VALUES (?, ?, ?, ?, datetime('now'))
             ON CONFLICT(hour_start) DO UPDATE SET
               busy_events = excluded.busy_events,
               max_queued = excluded.max_queued,
               fatal_events = excluded.fatal_events,
               created_at = excluded.created_at",
            [
                $hourLabel,
                (int)$parsed['busy_count'],
                (int)$parsed['max_queued'],
                (int)$parsed['fatal_count'],
            ]
        );
        Database::query(
            "DELETE FROM negotiate_helper_hourly WHERE hour_start < datetime('now', ?)",
            ['-' . self::RETAIN_DAYS . ' days']
        );

        $parsed['hour'] = $hourLabel;
        return $parsed;
    }

    /** No cron yet: record previous hour when Dashboard opens (flock, ≥50 min since last). */
    public static function maybeRecordHourly() {
        $auth = Database::fetch("SELECT 1 AS ok FROM auth_config WHERE scheme = 'negotiate' LIMIT 1");
        if (!$auth) {
            return;
        }
        $lockPath = (defined('SPM_STORAGE') ? SPM_STORAGE : '/opt/spm/storage') . '/tmp/negotiate_poll.lock';
        $dir = dirname($lockPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $fh = @fopen($lockPath, 'c');
        if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) {
            if ($fh) {
                fclose($fh);
            }
            return;
        }
        try {
            $last = Database::fetch("SELECT MAX(hour_start) AS h FROM negotiate_helper_hourly");
            $lastTs = !empty($last['h']) ? strtotime($last['h']) : 0;
            if ($lastTs > 0 && (time() - $lastTs) < 3000) {
                return;
            }
            self::recordHourlySample();
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    public static function aggregateHistory($hours) {
        $hours = max(1, (int)$hours);
        $row = Database::fetch(
            "SELECT COUNT(*) AS sample_count,
                    COALESCE(SUM(busy_events), 0) AS busy_events,
                    COALESCE(SUM(fatal_events), 0) AS fatal_events,
                    COALESCE(MAX(max_queued), 0) AS max_queued,
                    MAX(hour_start) AS last_hour
             FROM negotiate_helper_hourly
             WHERE hour_start >= datetime('now', ?)",
            ['-' . $hours . ' hours']
        ) ?: [];
        $peak7 = Database::fetch(
            "SELECT COALESCE(MAX(max_queued), 0) AS peak FROM negotiate_helper_hourly
             WHERE hour_start >= datetime('now', '-7 days')"
        ) ?: [];
        return [
            'sample_count' => (int)($row['sample_count'] ?? 0),
            'busy_events' => (int)($row['busy_events'] ?? 0),
            'fatal_events' => (int)($row['fatal_events'] ?? 0),
            'max_queued' => (int)($row['max_queued'] ?? 0),
            'last_hour' => (string)($row['last_hour'] ?? ''),
            'peak_queued_7d' => (int)($peak7['peak'] ?? 0),
            'window_h' => $hours,
        ];
    }

    public static function scanWindow($logPath, $sinceTs) {
        $readable = is_file($logPath) && is_readable($logPath);
        $text = $readable ? self::loadLogTail($logPath) : '';
        $out = self::parseChunkWindow($text, $sinceTs, null);
        $out['readable'] = $readable;
        return $out;
    }

    public static function parseChunkWindow($text, $sinceTs, $untilTs = null) {
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
            if ($ts !== null) {
                if ($ts < $sinceTs) {
                    $expectQueued = false;
                    continue;
                }
                if ($untilTs !== null && $ts >= $untilTs) {
                    $expectQueued = false;
                    continue;
                }
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

        return [
            'ok' => $busy === 0 && $fatal === 0,
            'readable' => true,
            'busy_count' => $busy,
            'fatal_count' => $fatal,
            'max_queued' => $maxQueued,
            'last_busy' => $lastBusyLabel,
            'busy_ratio' => ($busyN !== null && $busyD !== null) ? ($busyN . '/' . $busyD) : '',
            'window_h' => 24,
        ];
    }

    /** @deprecated use parseChunkWindow */
    public static function parseChunk($text, $sinceTs) {
        return self::parseChunkWindow($text, $sinceTs, null);
    }

    private static function loadLogTail($logPath) {
        $text = self::tailBytes($logPath, self::TAIL_BYTES);
        if (is_readable($logPath . '.1')) {
            $text = self::tailBytes($logPath . '.1', (int)(self::TAIL_BYTES / 2)) . "\n" . $text;
        }
        return $text;
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
