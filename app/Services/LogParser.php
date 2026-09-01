<?php
class LogParser {
    /** Max bytes read from end of access.log for UI search (newest window). */
    private const FILTER_SCAN_BYTES = 16777216; // 16 MiB

    /** Larger window for CSV export. */
    private const EXPORT_SCAN_BYTES = 67108864; // 64 MiB
    /**
     * Parse Squid access.log (native format)
     * timestamp elapsed remotehost code/status bytes method URL rfc931 peerstatus/peerhost type
     */
    public static function parseLine($line) {
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) < 9) return null;

        // peer field: hierarchy/peerhost (e.g. DIRECT/172.26.13.1 or PARENT_HIT/ksmg)
        $peerRaw = $parts[8] ?? '';
        $hierarchy = '';
        $peerHost = '';
        if (strpos($peerRaw, '/') !== false) {
            list($hierarchy, $peerHost) = explode('/', $peerRaw, 2);
        } else {
            $hierarchy = $peerRaw;
        }

        return [
            'timestamp' => isset($parts[0]) ? date('Y-m-d H:i:s', (int)$parts[0]) : null,
            'timestamp_unix' => isset($parts[0]) ? (int)$parts[0] : 0,
            'elapsed' => $parts[1] ?? 0,
            'client_ip' => $parts[2] ?? '',
            'status' => $parts[3] ?? '',
            'bytes' => $parts[4] ?? 0,
            'method' => $parts[5] ?? '',
            'url' => $parts[6] ?? '',
            'user' => $parts[7] !== '-' ? $parts[7] : '',
            'peer' => $peerRaw,
            'hierarchy' => $hierarchy,
            'peer_host' => $peerHost,
            'content_type' => $parts[9] ?? '',
        ];
    }

    public static function tail($file, $lines = 100) {
        if (!file_exists($file) || !is_readable($file)) {
            return [];
        }

        $text = self::tailBytes($file, self::FILTER_SCAN_BYTES);
        if ($text === '') {
            return [];
        }

        $text = self::dropPartialHeadLine($file, $text, self::FILTER_SCAN_BYTES);
        $rawLines = explode("\n", $text);
        $output = [];
        for ($i = count($rawLines) - 1; $i >= 0 && count($output) < $lines; $i--) {
            $line = trim($rawLines[$i]);
            if ($line === '') {
                continue;
            }
            $parsed = self::parseLine($line);
            if ($parsed) {
                $output[] = $parsed;
            }
        }

        return $output;
    }

    public static function filter($file, $filters = [], $limit = 500, $maxScanBytes = null) {
        if (!file_exists($file) || !is_readable($file)) {
            return [];
        }

        $limit = max(1, (int)$limit);
        $maxScanBytes = max(65536, (int)($maxScanBytes ?? self::FILTER_SCAN_BYTES));

        $text = self::tailBytes($file, $maxScanBytes);
        if ($text === '') {
            return [];
        }

        $text = self::dropPartialHeadLine($file, $text, $maxScanBytes);
        $rawLines = explode("\n", $text);
        $results = [];
        for ($i = count($rawLines) - 1; $i >= 0 && count($results) < $limit; $i--) {
            $line = trim($rawLines[$i]);
            if ($line === '') {
                continue;
            }
            $parsed = self::parseLine($line);
            if ($parsed && self::matchesFilters($parsed, $filters)) {
                $results[] = $parsed;
            }
        }

        return $results;
    }

    public static function exportScanBytes() {
        return self::EXPORT_SCAN_BYTES;
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

    /** First line after tail chunk may be truncated when file is larger than the window. */
    private static function dropPartialHeadLine($path, $text, $maxScanBytes) {
        $size = filesize($path);
        if ($size === false || $size <= $maxScanBytes) {
            return $text;
        }
        $nl = strpos($text, "\n");
        return $nl === false ? '' : substr($text, $nl + 1);
    }

    private static function matchesFilters(array $parsed, array $filters) {
        if (!empty($filters['ip']) && strpos($parsed['client_ip'], $filters['ip']) === false) {
            return false;
        }
        if (!empty($filters['user']) && strpos($parsed['user'], $filters['user']) === false) {
            return false;
        }
        if (!empty($filters['status']) && strpos($parsed['status'], $filters['status']) === false) {
            return false;
        }
        if (!empty($filters['url']) && strpos($parsed['url'], $filters['url']) === false) {
            return false;
        }
        if (!empty($filters['method']) && $parsed['method'] !== $filters['method']) {
            return false;
        }
        if (!empty($filters['peer'])) {
            if ($filters['peer'] === 'DIRECT') {
                $hierarchy = $parsed['hierarchy'] ?? '';
                $peerHost = $parsed['peer_host'] ?? '';
                if (!in_array($hierarchy, ['DIRECT', 'NONE', ''], true) && $peerHost !== '' && $peerHost !== '-') {
                    return false;
                }
            } else {
                $peerHost = $parsed['peer_host'] ?? '';
                $matchName = (string)$filters['peer'];
                $matchHostname = (string)($filters['peer_hostname'] ?? '');
                $nameMatch = strcasecmp($peerHost, $matchName) === 0;
                $hostMatch = $matchHostname !== '' && strcasecmp($peerHost, $matchHostname) === 0;
                if (!$nameMatch && !$hostMatch) {
                    return false;
                }
            }
        }
        return true;
    }

    public static function getStats($file, $hours = 24) {
        $hours = max(1, min(168, (int)$hours));
        $empty = self::emptyStats($hours);

        if (!file_exists($file)) {
            $empty['error'] = 'Access log not found';
            return $empty;
        }
        if (!is_readable($file)) {
            $empty['error'] = 'Access log is not readable by the panel user';
            return $empty;
        }

        $handle = fopen($file, 'r');
        if (!$handle) {
            $empty['error'] = 'Could not open access log';
            return $empty;
        }

        $domains = [];
        $users = [];
        $codes = [];
        $hourly = [];
        $now = time();
        $cutoff = $now - ($hours * 3600);
        for ($i = $hours - 1; $i >= 0; $i--) {
            $ts = $now - ($i * 3600);
            $key = date('YmdH', $ts);
            $hourly[$key] = ['hour' => date('H', $ts), 'count' => 0];
        }

        $total = 0;
        $hits = 0;
        $misses = 0;
        $errors = 0;

        while (($line = fgets($handle)) !== false) {
            $parsed = self::parseLine($line);
            if (!$parsed || empty($parsed['timestamp'])) {
                continue;
            }

            $ts = strtotime($parsed['timestamp']);
            if ($ts === false || $ts < $cutoff) {
                continue;
            }

            $total++;
            $hourKey = date('YmdH', $ts);
            if (isset($hourly[$hourKey])) {
                $hourly[$hourKey]['count']++;
            }

            $host = self::requestHost($parsed['url'] ?? '');
            $domains[$host] = ($domains[$host] ?? 0) + 1;

            if (!empty($parsed['user'])) {
                $users[$parsed['user']] = ($users[$parsed['user']] ?? 0) + (int)$parsed['bytes'];
            }

            $statusParts = explode('/', $parsed['status'] ?? '');
            $hier = $statusParts[0] ?? 'unknown';
            $http = $statusParts[1] ?? '';
            $codes[$hier] = ($codes[$hier] ?? 0) + 1;

            if (strpos($hier, 'HIT') !== false) {
                $hits++;
            } elseif (strpos($hier, 'ERR_') === 0 || ((int)$http >= 500 && (int)$http <= 599)) {
                $errors++;
            } else {
                $misses++;
            }
        }

        fclose($handle);

        arsort($domains);
        arsort($users);
        arsort($codes);

        $top = array_slice($domains, 0, 10, true);
        $topDomains = [];
        foreach ($top as $domain => $count) {
            $topDomains[] = ['domain' => (string)$domain, 'count' => (int)$count];
        }

        $ratio = $total > 0 ? round(($hits / $total) * 100) . '%' : '0%';

        return [
            'domains' => $top,
            'topDomains' => $topDomains,
            'hourly' => array_values($hourly),
            'users' => array_slice($users, 0, 10, true),
            'codes' => array_slice($codes, 0, 10, true),
            'total_requests' => $total,
            'cache_hits' => $hits,
            'cache_misses' => $misses,
            'errors' => $errors,
            'hit_ratio' => $ratio,
        ];
    }

    private static function emptyStats($hours) {
        $hourly = [];
        $now = time();
        for ($i = $hours - 1; $i >= 0; $i--) {
            $ts = $now - ($i * 3600);
            $hourly[] = ['hour' => date('H', $ts), 'count' => 0];
        }
        return [
            'domains' => [],
            'topDomains' => [],
            'hourly' => $hourly,
            'users' => [],
            'codes' => [],
            'total_requests' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'errors' => 0,
            'hit_ratio' => '0%',
        ];
    }

    private static function requestHost($url) {
        $host = parse_url($url, PHP_URL_HOST);
        if (!empty($host)) {
            return $host;
        }
        if (preg_match('#^https?://([^/:]+)#i', $url, $m)) {
            return $m[1];
        }
        if (preg_match('#^([^/:\s]+)(?::\d+)?$#', $url, $m)) {
            return $m[1];
        }
        return 'unknown';
    }

    /**
     * Get hierarchy type label
     */
    public static function hierarchyLabel($hierarchy) {
        $direct = ['DIRECT', 'NONE'];
        $peer = ['PARENT_HIT', 'SIBLING_HIT', 'DEFAULT_PARENT', 'FIRST_UP_PARENT', 'ROUNDROBIN_PARENT', 'CLOSEST_PARENT'];

        if (in_array($hierarchy, $direct)) {
            return ['label' => 'Direct', 'class' => 'badge-success'];
        } elseif (in_array($hierarchy, $peer)) {
            return ['label' => 'Peer', 'class' => 'badge-parent'];
        } else {
            return ['label' => $hierarchy ?: 'Unknown', 'class' => ''];
        }
    }
}
