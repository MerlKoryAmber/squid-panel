<?php
class SquidSyntaxChecker {
    public static function validateFile($path) {
        if (!file_exists($path)) {
            return ['valid' => false, 'error' => 'File not found'];
        }

        try {
            $result = PrivilegedExecutor::execute('squid_syntax');
            if ($result['success']) {
                return ['valid' => true, 'error' => null];
            }
            return ['valid' => false, 'error' => $result['stderr']];
        } catch (Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    public static function validateConfig() {
        return self::validateFile(SQUID_CONF);
    }
}
