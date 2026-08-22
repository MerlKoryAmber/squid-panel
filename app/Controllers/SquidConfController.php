<?php
class SquidConfController {
    public const MAX_BYTES = 2097152;

    public function index($params = []) {
        Auth::requireAuth();
        $path = defined('SQUID_CONF') ? SQUID_CONF : '/etc/squid/squid.conf';
        $error = '';
        $body = '';
        $bytes = 0;
        $mtime = '';

        if (!is_file($path)) {
            $error = 'Live config not found: ' . $path;
        } elseif (!is_readable($path)) {
            $error = 'Live config is not readable: ' . $path;
        } else {
            $size = filesize($path);
            $bytes = $size === false ? 0 : (int)$size;
            $mt = filemtime($path);
            if ($mt) {
                $mtime = gmdate('Y-m-d H:i:s', $mt) . ' UTC';
            }
            if ($bytes === 0) {
                $error = 'Live config is empty: ' . $path;
            } elseif ($bytes > self::MAX_BYTES) {
                $raw = file_get_contents($path, false, null, 0, self::MAX_BYTES);
                $body = is_string($raw) ? $raw : '';
                $error = 'File is larger than 2 MiB; showing the first 2 MiB only.';
            } else {
                $raw = file_get_contents($path);
                if ($raw === false) {
                    $error = 'Failed to read ' . $path;
                } else {
                    $body = $raw;
                }
            }
        }

        echo View::render('squid_conf.index', [
            'title' => 'Live config',
            'active' => 'live_config',
            'path' => $path,
            'body' => $body,
            'error' => $error,
            'bytes' => $bytes,
            'mtime' => $mtime,
        ]);
    }
}
