<?php
class SquidSyntaxChecker {
    public static function validateFile($path) {
        $path = (string)$path;
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return ['valid' => false, 'error' => 'File not found or not readable'];
        }

        $staging = defined('SQUID_PARSE_FILE')
            ? SQUID_PARSE_FILE
            : '/opt/spm/storage/tmp/squid.conf.parse';
        $dir = dirname($staging);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            return ['valid' => false, 'error' => 'Cannot create parse staging directory'];
        }
        if (!@copy($path, $staging)) {
            return ['valid' => false, 'error' => 'Cannot stage config for squid -k parse'];
        }
        @chmod($staging, 0640);

        try {
            $result = PrivilegedExecutor::execute('squid_syntax');
            $detail = trim((string)(($result['stderr'] ?? '') . "\n" . ($result['stdout'] ?? '')));
            if (!empty($result['success'])) {
                return ['valid' => true, 'error' => null];
            }
            return ['valid' => false, 'error' => $detail !== '' ? $detail : 'squid -k parse failed'];
        } catch (Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    public static function validateConfig() {
        return self::validateFile(SQUID_CONF);
    }
}
