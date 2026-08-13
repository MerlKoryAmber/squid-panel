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
        'squid_syntax' => ['/usr/sbin/squid', '-k', 'parse'],
        'squid_version' => ['/usr/sbin/squid', '-v'],
        'winbind_status' => ['/usr/bin/systemctl', 'status', 'winbind', '--no-pager', '-o', 'short'],
        'kinit_test' => ['/usr/bin/kinit', '-k', '-t'],
        'wbinfo_test' => ['/usr/bin/wbinfo', '-t'],
        'net_ads_info' => ['/usr/bin/net', 'ads', 'info'],
    ];

    public static function execute($commandKey, $extraArgs = []) {
        if (!isset(self::$allowedCommands[$commandKey])) {
            throw new Exception("Command not allowed: {$commandKey}");
        }

        $cmd = self::$allowedCommands[$commandKey];
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
            return null; // Fallback to sudo
        }

        @socket_write($socket, $payload);
        @socket_shutdown($socket, SHUT_WR);

        $response = '';
        while ($buf = @socket_read($socket, 4096)) {
            if ($buf === false) break;
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
        $fullCmd = '/usr/bin/sudo -n ' . implode(' ', $escaped) . ' 2>&1';
        $stdout = shell_exec($fullCmd);
        $exitCode = ($stdout === null) ? 1 : 0;

        return [
            'success' => $exitCode === 0,
            'exit_code' => $exitCode,
            'stdout' => $stdout ?: '',
            'stderr' => '',
        ];
    }

    public static function getSquidStatus() {
        try {
            $result = self::execute('squid_status');
            if ($result['success']) {
                $lines = explode("
", $result['stdout']);
                $status = 'unknown';
                foreach ($lines as $line) {
                    if (strpos($line, 'Active:') !== false) {
                        if (strpos($line, 'active (running)') !== false) {
                            $status = 'running';
                        } elseif (strpos($line, 'inactive') !== false) {
                            $status = 'stopped';
                        } else {
                            $status = 'error';
                        }
                    }
                }
                return ['status' => $status, 'raw' => $result['stdout']];
            }
            // Fallback: try pgrep / ps
            $pgrep = @shell_exec('pgrep -x squid 2>/dev/null');
            if (!empty(trim($pgrep))) {
                return ['status' => 'running', 'raw' => 'squid process found via pgrep'];
            }
            $ps = @shell_exec('ps aux | grep "[s]quid" 2>/dev/null');
            if (!empty(trim($ps))) {
                return ['status' => 'running', 'raw' => "squid process found:
" . $ps];
            }
            $errorDetail = $result['stderr'] ?: $result['stdout'];
            if (empty($errorDetail)) {
                $errorDetail = 'Command returned no output. Check if squid is installed and sudoers configured.';
            }
            return ['status' => 'error', 'raw' => $errorDetail];
        } catch (Exception $e) {
            return ['status' => 'error', 'raw' => $e->getMessage()];
        }
    }

    public static function getSquidVersion() {
        try {
            $result = self::execute('squid_version');
            if ($result['success']) {
                preg_match('/Version\s+([0-9.]+)/', $result['stdout'], $matches);
                if (!empty($matches[1])) {
                    return $matches[1];
                }
                // Debug: if regex fails, return raw output for inspection
                return 'raw:' . substr($result['stdout'], 0, 100);
            }
            // Debug: return error info
            return 'err:' . substr($result['stdout'] . $result['stderr'], 0, 100);
        } catch (Exception $e) {
            return 'exc:' . substr($e->getMessage(), 0, 100);
        }
    }
}
