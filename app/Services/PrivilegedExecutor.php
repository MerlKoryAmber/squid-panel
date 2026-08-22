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
        'squid_status' => ['/usr/bin/systemctl', 'is-active', 'squid'],
        'squid_syntax' => ['/usr/sbin/squid', '-f', '/opt/spm/storage/tmp/squid.conf.parse', '-k', 'parse'],
        'squid_version' => ['/usr/sbin/squid', '-v'],
        'winbind_status' => ['/usr/bin/systemctl', 'is-active', 'winbind'],
        'kinit_test' => ['/usr/bin/kinit', '-k', '-t'],
        'wbinfo_test' => ['/usr/bin/wbinfo', '-t'],
        'wbinfo_groups' => ['/usr/bin/wbinfo', '-g'],
        'net_ads_info' => ['/usr/bin/net', 'ads', 'info'],
        'acl_file_install' => ['__acl_file_install__'],
        'keytab_install' => ['__keytab_install__'],
        'ad_ldap_groups' => ['__ad_ldap_groups__'],
        'squid_listen_apply' => ['__squid_listen_apply__'],
        'squid_policy_apply' => ['__squid_policy_apply__'],
        'nginx_allow_apply' => ['__nginx_allow_apply__'],
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
        } elseif ($commandKey === 'acl_file_install') {
            $extraArgs = [AclListFile::fileName(preg_replace('/\.txt$/', '', (string)($extraArgs[0] ?? '')))];
        } elseif ($commandKey === 'keytab_install') {
            $extraArgs = [basename(self::squidKeytabPath($extraArgs[0] ?? '', false))];
        } elseif ($commandKey === 'ad_ldap_groups') {
            $extraArgs = AdGroupAcl::ldapQueryArgs();
        } elseif ($commandKey === 'squid_listen_apply' || $commandKey === 'squid_policy_apply' || $commandKey === 'nginx_allow_apply') {
            $extraArgs = [];
        } elseif ($commandKey === 'squid_syntax') {
            $extraArgs = [];
        } elseif (!empty($extraArgs)) {
            throw new Exception('Extra arguments are not allowed');
        }

        if ($commandKey === 'acl_file_install' || $commandKey === 'keytab_install' || $commandKey === 'ad_ldap_groups'
            || $commandKey === 'squid_listen_apply' || $commandKey === 'squid_policy_apply' || $commandKey === 'nginx_allow_apply') {
            $recv = 10;
            if ($commandKey === 'ad_ldap_groups' || $commandKey === 'squid_listen_apply' || $commandKey === 'squid_policy_apply') {
                $recv = 45;
            }
            if (AGENT_ENABLED && file_exists(AGENT_SOCKET)) {
                $result = self::executeViaAgent($commandKey, $extraArgs, $recv);
                if ($result !== null) {
                    return $result;
                }
            }
            if ($commandKey === 'keytab_install') {
                $need = 'spmd is required to install keytabs into /etc/squid';
            } elseif ($commandKey === 'ad_ldap_groups') {
                $need = 'spmd is required to list AD groups via LDAP';
            } elseif ($commandKey === 'squid_listen_apply') {
                $need = 'spmd is required to apply Squid listen settings';
            } elseif ($commandKey === 'squid_policy_apply') {
                $need = 'spmd is required to apply live squid.conf';
            } elseif ($commandKey === 'nginx_allow_apply') {
                $need = 'spmd is required to apply nginx IP allowlist';
            } else {
                $need = 'spmd is required to copy ACL lists into /etc/squid/acl.d';
            }
            return [
                'success' => false,
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => $need,
            ];
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

    private static function executeViaAgent($commandKey, $extraArgs, $recvSec = 10) {
        $flags = JSON_UNESCAPED_SLASHES;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        $payload = json_encode([
            'command' => $commandKey,
            'args' => array_values($extraArgs),
            'timestamp' => time(),
        ], $flags);
        if (!is_string($payload) || $payload === '') {
            return [
                'success' => false,
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => 'Failed to encode agent request',
            ];
        }

        $socket = @socket_create(AF_UNIX, SOCK_STREAM, 0);
        if (!$socket || !@socket_connect($socket, AGENT_SOCKET)) {
            if ($socket) @socket_close($socket);
            return null; // Fallback to sudo
        }

        $recvSec = (int)$recvSec;
        if ($recvSec < 5) {
            $recvSec = 5;
        }
        @socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 5, 'usec' => 0]);
        @socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $recvSec, 'usec' => 0]);

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

        $how = defined('SHUT_WR') ? SHUT_WR : 1;
        @socket_shutdown($socket, $how);

        $response = '';
        while (true) {
            $buf = @socket_read($socket, 4096);
            if ($buf === false || $buf === '') break;
            $response .= $buf;
        }
        @socket_close($socket);

        $result = json_decode($response, true);
        if (!is_array($result) || !array_key_exists('success', $result)) {
            return null;
        }
        if (!empty($result['error']) && !isset($result['exit_code'])) {
            $result['success'] = false;
            $result['exit_code'] = 1;
            if (!isset($result['stderr']) || $result['stderr'] === '') {
                $result['stderr'] = (string)$result['error'];
            }
            if (!isset($result['stdout'])) {
                $result['stdout'] = '';
            }
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

        $success = $exitCode === 0;
        $cmd0 = $cmd[1] ?? '';
        if ($cmd0 === 'is-active' && in_array($exitCode, [0, 3, 4], true)) {
            $success = true;
        }

        return [
            'success' => $success,
            'exit_code' => $exitCode,
            'stdout' => rtrim($body),
            'stderr' => '',
        ];
    }

    public static function getSquidStatus() {
        $pid = self::squidPid();
        $svcState = 'unknown';

        if ($pid <= 0) {
            try {
                $result = self::execute('squid_status');
                $word = strtolower(trim((string)($result['stdout'] ?? '')));
                $word = preg_split('/\s+/', $word, 2)[0] ?? '';
                if ($word === 'active') {
                    $svcState = 'running';
                } elseif (in_array($word, ['inactive', 'dead'], true)) {
                    $svcState = 'stopped';
                } elseif ($word === 'failed') {
                    $svcState = 'error';
                } elseif (in_array($word, ['activating', 'deactivating', 'reloading'], true)) {
                    $svcState = 'running';
                }
            } catch (Exception $e) {
                $svcState = 'unknown';
            }
            if ($svcState === 'running') {
                $pid = self::squidPid();
            }
        } else {
            $svcState = 'running';
        }

        $running = ($svcState === 'running') || ($pid > 0);
        if ($running) {
            $status = 'running';
        } elseif ($svcState === 'stopped') {
            $status = 'stopped';
        } else {
            $status = 'error';
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
        ];
    }

    private static function squidPid() {
        foreach (['/run/squid.pid', '/run/squid/squid.pid', '/var/run/squid.pid'] as $file) {
            $raw = @file_get_contents($file);
            $pid = (int)trim((string)$raw);
            if ($pid > 0 && @is_dir('/proc/' . $pid) && self::procComm($pid) === 'squid') {
                return $pid;
            }
        }

        $best = 0;
        $comms = @glob('/proc/[0-9]*/comm');
        if (is_array($comms)) {
            foreach ($comms as $commFile) {
                $name = trim((string)@file_get_contents($commFile));
                if ($name !== 'squid') {
                    continue;
                }
                $pid = (int)basename(dirname($commFile));
                if ($pid > 0 && ($best === 0 || $pid < $best)) {
                    $best = $pid;
                }
            }
        }
        if ($best > 0) {
            return $best;
        }

        $out = @shell_exec('pgrep -xo squid 2>/dev/null');
        $pid = (int)trim((string)$out);
        return $pid > 0 ? $pid : 0;
    }

    private static function procComm($pid) {
        return trim((string)@file_get_contents('/proc/' . (int)$pid . '/comm'));
    }

    private static function formatElapsed($seconds) {
        $seconds = (int)$seconds;
        if ($seconds < 0) {
            return null;
        }
        $d = intdiv($seconds, 86400);
        $h = intdiv($seconds % 86400, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        if ($d > 0) {
            return sprintf('%d-%02d:%02d:%02d', $d, $h, $m, $s);
        }
        if ($h > 0) {
            return sprintf('%02d:%02d:%02d', $h, $m, $s);
        }
        return sprintf('%02d:%02d', $m, $s);
    }

    private static function procEtime($pid) {
        $uptimeRaw = @file_get_contents('/proc/uptime');
        $stat = @file_get_contents('/proc/' . (int)$pid . '/stat');
        if ($uptimeRaw === false || $stat === false) {
            return null;
        }
        $uptime = (float)strtok($uptimeRaw, ' ');
        $rparen = strrpos($stat, ')');
        if ($rparen === false) {
            return null;
        }
        $fields = explode(' ', trim(substr($stat, $rparen + 1)));
        $startTicks = (int)($fields[19] ?? 0);
        $elapsed = (int)max(0, $uptime - ($startTicks / 100.0));
        return self::formatElapsed($elapsed);
    }

    private static function squidProcessMetrics($pid) {
        $pid = (int)$pid;
        $memory = null;
        $status = @file_get_contents('/proc/' . $pid . '/status');
        if (is_string($status) && preg_match('/^VmRSS:\s+(\d+)\s+kB/m', $status, $m)) {
            $rssKb = (int)$m[1];
            if ($rssKb >= 1024) {
                $memory = sprintf('%.1f MB', $rssKb / 1024);
            } elseif ($rssKb > 0) {
                $memory = $rssKb . ' KB';
            }
        }
        $etime = self::procEtime($pid);
        $cpu = null;
        $line = @shell_exec('ps -p ' . $pid . ' -o etime=,pcpu=,rss= --no-headers 2>/dev/null');
        $line = trim((string)$line);
        if ($line !== '') {
            $parts = preg_split('/\s+/', $line);
            if (!empty($parts[0])) {
                $etime = $parts[0];
            }
            if (isset($parts[1])) {
                $cpu = rtrim($parts[1], '%');
            }
            if ($memory === null && isset($parts[2])) {
                $rssKb = (int)$parts[2];
                if ($rssKb >= 1024) {
                    $memory = sprintf('%.1f MB', $rssKb / 1024);
                } elseif ($rssKb > 0) {
                    $memory = $rssKb . ' KB';
                }
            }
        }
        return [
            'etime' => $etime,
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
