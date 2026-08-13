<?php
class LogParser {
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

        $output = [];
        $handle = fopen($file, 'r');
        if (!$handle) return [];

        fseek($handle, 0, SEEK_END);
        $pos = ftell($handle);
        $count = 0;
        $buffer = '';

        while ($pos > 0 && $count < $lines) {
            $pos--;
            fseek($handle, $pos);
            $char = fgetc($handle);
            if ($char === "
") {
                if (!empty($buffer)) {
                    $parsed = self::parseLine(strrev($buffer));
                    if ($parsed) $output[] = $parsed;
                    $count++;
                    $buffer = '';
                }
            } else {
                $buffer .= $char;
            }
        }

        fclose($handle);
        return array_reverse($output);
    }

    public static function filter($file, $filters = [], $limit = 500) {
        if (!file_exists($file) || !is_readable($file)) {
            return [];
        }

        $results = [];
        $handle = fopen($file, 'r');
        if (!$handle) return [];

        while (($line = fgets($handle)) !== false && count($results) < $limit) {
            $parsed = self::parseLine($line);
            if (!$parsed) continue;

            if (!empty($filters['ip']) && strpos($parsed['client_ip'], $filters['ip']) === false) continue;
            if (!empty($filters['user']) && strpos($parsed['user'], $filters['user']) === false) continue;
            if (!empty($filters['status']) && strpos($parsed['status'], $filters['status']) === false) continue;
            if (!empty($filters['url']) && strpos($parsed['url'], $filters['url']) === false) continue;
            if (!empty($filters['method']) && $parsed['method'] !== $filters['method']) continue;

            $results[] = $parsed;
        }

        fclose($handle);
        return $results;
    }

    public static function getStats($file, $hours = 24) {
        if (!file_exists($file)) return ['domains' => [], 'users' => [], 'codes' => []];

        $domains = [];
        $users = [];
        $codes = [];
        $cutoff = time() - ($hours * 3600);

        $handle = fopen($file, 'r');
        if (!$handle) return ['domains' => [], 'users' => [], 'codes' => []];

        while (($line = fgets($handle)) !== false) {
            $parsed = self::parseLine($line);
            if (!$parsed || empty($parsed['timestamp'])) continue;

            $ts = strtotime($parsed['timestamp']);
            if ($ts < $cutoff) continue;

            $host = parse_url($parsed['url'], PHP_URL_HOST) ?: 'unknown';
            $domains[$host] = ($domains[$host] ?? 0) + 1;

            if (!empty($parsed['user'])) {
                $users[$parsed['user']] = ($users[$parsed['user']] ?? 0) + (int)$parsed['bytes'];
            }

            $code = explode('/', $parsed['status'])[0] ?? 'unknown';
            $codes[$code] = ($codes[$code] ?? 0) + 1;
        }

        fclose($handle);

        arsort($domains);
        arsort($users);
        arsort($codes);

        return [
            'domains' => array_slice($domains, 0, 10, true),
            'users' => array_slice($users, 0, 10, true),
            'codes' => array_slice($codes, 0, 10, true),
        ];
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
