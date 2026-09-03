<?php
/**
 * Panel nginx TLS + LDAP CA trust paths (ADR 0007).
 */
class PanelTls {
    public const PANEL_CERT = '/etc/pki/tls/certs/spm-selfsigned.crt';
    public const PANEL_KEY = '/etc/pki/tls/private/spm-selfsigned.key';
    public const STAGE_CERT = 'spm-panel.crt';
    public const STAGE_KEY = 'spm-panel.key';
    public const LDAP_CA = '/etc/squid/spm-ldap-ca.pem';
    public const STAGE_CA = 'spm-ldap-ca.pem';
    public const MAX_PEM = 262144;

    public static function ldapCaInstalled() {
        return is_readable(self::LDAP_CA) && (int)@filesize(self::LDAP_CA) > 64;
    }

    public static function panelCertPresent() {
        return is_readable(self::PANEL_CERT) && (int)@filesize(self::PANEL_CERT) > 64;
    }

    public static function panelCertSubject() {
        if (!self::panelCertPresent() || !is_executable('/usr/bin/openssl')) {
            return '';
        }
        $out = @shell_exec(
            '/usr/bin/openssl x509 -in ' . escapeshellarg(self::PANEL_CERT)
            . ' -noout -subject -enddate 2>/dev/null'
        );
        return trim((string)$out);
    }

    public static function assertPemCert($raw) {
        $raw = (string)$raw;
        if (strlen($raw) < 64 || strlen($raw) > self::MAX_PEM) {
            throw new Exception('Certificate must be between 64 bytes and 256 KB');
        }
        if (strpos($raw, '-----BEGIN CERTIFICATE-----') === false
            || strpos($raw, '-----END CERTIFICATE-----') === false) {
            throw new Exception('File is not a PEM certificate');
        }
        if (strpos($raw, "\0") !== false) {
            throw new Exception('Certificate contains NUL');
        }
    }

    public static function assertPemKey($raw) {
        $raw = (string)$raw;
        if (strlen($raw) < 64 || strlen($raw) > self::MAX_PEM) {
            throw new Exception('Private key must be between 64 bytes and 256 KB');
        }
        $ok = (strpos($raw, '-----BEGIN PRIVATE KEY-----') !== false
                && strpos($raw, '-----END PRIVATE KEY-----') !== false)
            || (strpos($raw, '-----BEGIN RSA PRIVATE KEY-----') !== false
                && strpos($raw, '-----END RSA PRIVATE KEY-----') !== false)
            || (strpos($raw, '-----BEGIN EC PRIVATE KEY-----') !== false
                && strpos($raw, '-----END EC PRIVATE KEY-----') !== false);
        if (!$ok) {
            throw new Exception('File is not a PEM private key');
        }
        if (strpos($raw, "\0") !== false) {
            throw new Exception('Private key contains NUL');
        }
    }

    public static function stageDir() {
        $dir = (defined('SPM_STORAGE') ? SPM_STORAGE : '/opt/spm/storage') . '/tmp';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new Exception('Cannot create staging directory');
        }
        return $dir;
    }

    public static function writeStage($name, $raw) {
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
            throw new Exception('Invalid staging name');
        }
        $path = self::stageDir() . '/' . $name;
        if (file_put_contents($path, $raw) === false) {
            throw new Exception('Cannot write staging file');
        }
        @chmod($path, 0600);
        return $name;
    }
}
