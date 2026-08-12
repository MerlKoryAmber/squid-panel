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
            if (preg_match('/[;&|<>$`\\]/', $arg)) {
                throw new Exception("Invalid character in command argument");
            }
        }

        // Try agent first
        if (AGENT_ENABLED && file_exists(AGENT_SOCKET)) {
            return self::executeViaAgent($commandKey, $extraArgs);
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

        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if (!@socket_connect($socket, AGENT_SOCKET)) {
            throw new Exception("Cannot connect to agent");
        }

        socket_write($socket, $payload);
        socket_shutdown($socket, SHUT_WR);

        $response = '';
        while ($buf = socket_read($socket, 4096)) {
            $response .= $buf;
        }
        socket_close($socket);

        $result = json_decode($response, true);
        if (!$result || !isset($result['success'])) {
            throw new Exception("Invalid agent response");
        }

        return $result;
    }

    private static function executeViaSudo($cmd) {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $fullCmd = array_merge(['/usr/bin/sudo', '-n'], $cmd);
        $process = proc_open($fullCmd, $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new Exception("Failed to execute command");
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'success' => $exitCode === 0,
            'exit_code' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
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
            return ['status' => 'error', 'raw' => $result['stderr']];
        } catch (Exception $e) {
            return ['status' => 'error', 'raw' => $e->getMessage()];
        }
    }

    public static function getSquidVersion() {
        try {
            $result = self::execute('squid_version');
            if ($result['success']) {
                preg_match('/Version ([0-9.]+)/', $result['stdout'], $matches);
                return $matches[1] ?? 'unknown';
            }
            return 'unknown';
        } catch (Exception $e) {
            return 'unknown';
        }
    }
}
