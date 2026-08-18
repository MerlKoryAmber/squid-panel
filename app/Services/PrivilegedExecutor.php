<?php
/**
 * Secure command executor via privileged agent or sudo
 */
class PrivilegedExecutor {
    private static $allowedCommands = [
        'squid_reconfigure' => ['/usr/sbin/squid', '-k', 'reconfigure'],
        'squid_restart' => ['/usr/bin/systemctl', 'restart', 'squid'],
        'squid_start' => ['/usr/bin/systemctl', 'start', 'squid'],
        'squid_stop' => ['/usr/bin/systemctl', 'stop', 'squid'],
        'squid_status' => ['/usr/bin/systemctl', 'status', 'squid', '--no-pager', '-o', 'short'],
        'squid_syntax' => ['/usr/sbin/squid', '-f', '/opt/spm/storage/tmp/squid.conf.parse', '-k', 'parse'],
        'squid_version' => ['/usr/sbin/squid', '-v'],
        'winbind_status' => ['/usr/bin/systemctl', 'status', 'winbind', '--no-pager', '-o', 'short'],
        'kinit_test' => ['/usr/bin/kinit', '-k', '-t'],
        'wbinfo_test' => ['/usr/bin/wbinfo', '-t'],
        'net_ads_info' => ['/usr/bin/net', 'ads', 'info'],
    ];

    private const KEYTAB_DIR = '/etc/squid';

    public static function squidKeytabPath($path, $mustExist = false) {
        $path = trim((string)$path);
        if ($path === '') {
            $path = self::KEYTAB_DIR . '/proxy.keytab';
        }
        if (strpos($path, "\0") !== false) {
            throw new Exception('Invalid keytab path');
        }

        $base = basename($path);
        if (!preg_match('/^[A-Za-z0-9._-]+\.keytab$/', $base)) {
            throw new Exception('Keytab must be a .keytab file under /etc/squid');
        }

        $candidate = self::KEYTAB_DIR . '/' . $base;
        if ($mustExist) {
            $real = realpath($candidate);
            if ($real === false || dirname($real) !== self::KEYTAB_DIR || !is_file($real)) {
                throw new Exception('Keytab not found under /etc/squid');
            }
            return $real;
        }

        return $candidate;
    }

    public static function execute($commandKey, $extraArgs = []) {
        if (!isset(self::$allowedCommands[$commandKey])) {
            throw new Exception("Command not allowed: {$commandKey}");
        }

        if ($commandKey === 'kinit_test') {
            $extraArgs = [self::squidKeytabPath($extraArgs[0] ?? '', true)];
        } elseif ($commandKey === 'squid_syntax') {
            $extraArgs = [];
        } elseif (!empty($extraArgs)) {
            throw new Exception('Extra arguments are not allowed');
        }

        $cmd = self::$allowedCommands[$commandKey];
        if ($commandKey === 'squid_syntax') {
            $parseFile = defined('SQUID_PARSE_FILE') ? SQUID_PARSE_FILE : '/opt/spm/storage/tmp/squid.conf.parse';
            $cmd = ['/usr/sbin/squid', '-f', $parseFile, '-k', 'parse'];
        }
        if (!empty($extraArgs)) {
            $cmd = array_merge($cmd, $extraArgs);
        }

        // Validate all args against injection
        foreach ($cmd as $arg) {
            if (strpbrk($arg, ';|&<>$`') !== false) {
                throw new Exception("Invalid character in command argument");
            }
        }

        // Try agent first
        if (AGENT_ENABLED && file_exists(AGENT_SOCKET)) {
            $result = self::executeViaAgent($commandKey, $extraArgs);
            if ($result !== null) {
                return $result;
            }
            // Agent failed, fall through to sudo
        }

        // Fallback to sudo
        return self::executeViaSudo($cmd);
    }

    private static function executeViaAgent($commandKey, $extraArgs) {
        $payload = json_encode([
            'command' => $commandKey,
            'args' => $extraArgs,
            'timestamp' => time(),
        ]);

        $socket = @socket_create(AF_UNIX, SOCK_STREAM, 0);
        if (!$socket || !@socket_connect($socket, AGENT_SOCKET)) {
            if ($socket) @socket_close($socket);
            return null; // Fallback to sudo
        }

        // Set send/receive timeouts to prevent hung sockets
        @socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 5, 'usec' => 0]);
        @socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 10, 'usec' => 0]);

        // Ensure full payload is sent
        $sent = 0;
        $len = strlen($payload);
        while ($sent < $len) {
            $n = @socket_write($socket, substr($payload, $sent));
            if ($n === false) {
                @socket_close($socket);
                return null;
            }
            $sent += $n;
        }

        @socket_shutdown($socket, SHUT_WR);

        $response = '';
        while (true) {
            $buf = @socket_read($socket, 4096);
            if ($buf === false || $buf === '') break;
            $response .= $buf;
        }
        @socket_close($socket);

        $result = json_decode($response, true);
        if (!$result || !isset($result['success'])) {
            return null; // Fallback to sudo
        }

        return $result;
    }

    private static function executeViaSudo($cmd) {
        $escaped = array_map('escapeshellarg', $cmd);
        $fullCmd = '/usr/bin/sudo -n ' . implode(' ', $escaped) . ' 2>&1; echo __SPM_EXIT__:$?';
        $stdout = shell_exec($fullCmd);
        $exitCode = 1;
        $body = (string)$stdout;
        if (preg_match('/__SPM_EXIT__:(\d+)\s*$/', $body, $m)) {
            $exitCode = (int)$m[1];
            $body = preg_replace('/__SPM_EXIT__:\d+\s*$/', '', $body);
        } elseif ($stdout === null) {
            $exitCode = 1;
            $body = '';
        }

        return [
            'success' => $exitCode === 0,
            'exit_code' => $exitCode,
            'stdout' => rtrim($body),
            'stderr' => '',
        ];
    }

    public static function getSquidStatus() {
        $raw = '';
        $svcState = 'unknown';

        try {
            $result = self::execute('squid_status');
            $raw = $result['stdout'] ?? '';
            if (!empty($result['stderr'])) {
                $raw .= "\n" . $result['stderr'];
            }
            foreach (preg_split('/\r\n|\n|\r/', $raw) as $line) {
                if (strpos($line, 'Active:') === false) {
                    continue;
                }
                if (strpos($line, 'active (running)') !== false) {
                    $svcState = 'running';
                } elseif (strpos($line, 'inactive') !== false) {
                    $svcState = 'stopped';
                } else {
                    $svcState = 'error';
                }
                break;
            }
        } catch (Exception $e) {
            $raw = $e->getMessage();
            $svcState = 'error';
        }

        $pid = self::squidPid();
        $running = ($svcState === 'running') || ($pid > 0);
        if ($running) {
            $status = 'running';
        } elseif ($svcState === 'stopped') {
            $status = 'stopped';
        } else {
            $status = ($pid > 0) ? 'running' : 'error';
        }

        $metrics = $pid > 0 ? self::squidProcessMetrics($pid) : [];
        $port = self::httpListenPort();

        return [
            'status' => $status,
            'running' => $running,
            'pid' => $pid > 0 ? $pid : null,
            'uptime' => $metrics['etime'] ?? null,
            'cpu' => $metrics['cpu'] ?? null,
            'memory' => $metrics['memory'] ?? null,
            'connections' => $running ? self::countEstablishedOnPort($port) : 0,
            'raw' => $raw,
        ];
    }

    private static function squidPid() {
        $out = @shell_exec('pgrep -xo squid 2>/dev/null');
        $pid = (int)trim((string)$out);
        return $pid > 0 ? $pid : 0;
    }

    private static function squidProcessMetrics($pid) {
        $pid = (int)$pid;
        $line = @shell_exec('ps -p ' . $pid . ' -o etime=,pcpu=,rss= --no-headers 2>/dev/null');
        $line = trim((string)$line);
        if ($line === '') {
            return [];
        }
        $parts = preg_split('/\s+/', $line);
        $etime = $parts[0] ?? '';
        $cpu = isset($parts[1]) ? rtrim($parts[1], '%') : null;
        $rssKb = isset($parts[2]) ? (int)$parts[2] : 0;
        $memory = null;
        if ($rssKb >= 1024) {
            $memory = sprintf('%.1f MB', $rssKb / 1024);
        } elseif ($rssKb > 0) {
            $memory = $rssKb . ' KB';
        }
        return [
            'etime' => $etime !== '' ? $etime : null,
            'cpu' => $cpu,
            'memory' => $memory,
        ];
    }

    private static function httpListenPort() {
        try {
            $globals = Database::fetch("SELECT http_port FROM squid_globals LIMIT 1");
            $raw = $globals['http_port'] ?? '3128';
            if (preg_match('/(\d{2,5})/', (string)$raw, $m)) {
                $port = (int)$m[1];
                if ($port >= 1 && $port <= 65535) {
                    return $port;
                }
            }
        } catch (Exception $e) {
            // fall through
        }
        return 3128;
    }

    private static function countEstablishedOnPort($port) {
        $port = (int)$port;
        if ($port < 1 || $port > 65535) {
            return null;
        }
        $hex = sprintf('%04X', $port);
        $count = 0;
        $read = false;
        foreach (['/proc/net/tcp', '/proc/net/tcp6'] as $path) {
            $data = @file_get_contents($path);
            if ($data === false) {
                continue;
            }
            $read = true;
            foreach (explode("\n", $data) as $i => $line) {
                if ($i === 0 || $line === '') {
                    continue;
                }
                if (preg_match('/^\s*\d+:\s+[0-9A-Fa-f]+:' . $hex . '\s+[0-9A-Fa-f]+:[0-9A-Fa-f]+\s+01\b/i', $line)) {
                    $count++;
                }
            }
        }
        return $read ? $count : null;
    }
}
