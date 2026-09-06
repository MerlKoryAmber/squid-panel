<?php
/**
 * Managed Squid object-cache off switch (ADR 0008).
 * Emits cache deny all + cache_mem 0; skips cache_dir when disabled.
 */
class SquidCachePolicy {
    public const MARKER = '# SPM managed: object cache disabled';

    public static function isDisabled(array $globals = null) {
        if ($globals === null) {
            $globals = Database::fetch("SELECT disable_cache FROM squid_globals LIMIT 1") ?: [];
        }
        return !empty($globals['disable_cache']);
    }

    /** Remove SPM managed cache-off block from extra_conf text. */
    public static function stripManagedExtra($extra) {
        $extra = (string)$extra;
        if ($extra === '') {
            return '';
        }
        $lines = preg_split("/\r\n|\n|\r/", $extra);
        $out = [];
        $skip = false;
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === self::MARKER) {
                $skip = true;
                continue;
            }
            if ($skip) {
                if ($trim === '' || $trim === 'cache deny all' || preg_match('/^cache_mem\s+0\s*$/', $trim)) {
                    if ($trim === '') {
                        $skip = false;
                    }
                    continue;
                }
                $skip = false;
            }
            $out[] = $line;
        }
        return trim(implode("\n", $out));
    }

    public static function managedBlock() {
        return self::MARKER . "\ncache deny all\ncache_mem 0\n";
    }

    public static function setDisabled($disabled) {
        $disabled = $disabled ? 1 : 0;
        $row = Database::fetch("SELECT * FROM squid_globals LIMIT 1");
        if (!$row) {
            Database::query(
                "INSERT INTO squid_globals (http_port, disable_cache, extra_conf, updated_at) VALUES ('3128', ?, '', datetime('now'))",
                [$disabled]
            );
            return;
        }
        $extra = self::stripManagedExtra((string)($row['extra_conf'] ?? ''));
        $cacheDir = (string)($row['cache_dir'] ?? '');
        $saved = (string)($row['cache_dir_saved'] ?? '');
        if ($disabled) {
            if ($cacheDir !== '' && $saved === '') {
                $saved = $cacheDir;
            }
        } else {
            if ($cacheDir === '' && $saved !== '') {
                $cacheDir = $saved;
            }
        }
        Database::query(
            "UPDATE squid_globals SET disable_cache = ?, cache_dir = ?, cache_dir_saved = ?, extra_conf = ?, updated_at = datetime('now') WHERE id = ?",
            [$disabled, $cacheDir, $saved, $extra, (int)$row['id']]
        );
    }
}
