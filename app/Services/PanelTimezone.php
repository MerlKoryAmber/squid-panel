<?php
/**
 * Panel display timezone for PHP date() (Access Logs, audit, etc.).
 * Does not change Squid file logs or OS TZ.
 */
class PanelTimezone {
    public const DEFAULT = 'Europe/Moscow';

    /** @var string|null */
    private static $cached = null;

    /** @return array<string,string> id => label */
    public static function choices() {
        return [
            'Europe/Moscow' => 'Europe/Moscow (MSK, UTC+3)',
            'UTC' => 'UTC',
        ];
    }

    public static function normalize($raw) {
        $raw = trim((string)$raw);
        $choices = self::choices();
        if ($raw !== '' && isset($choices[$raw])) {
            return $raw;
        }
        return self::DEFAULT;
    }

    public static function current() {
        if (self::$cached !== null) {
            return self::$cached;
        }
        try {
            $row = Database::fetch("SELECT timezone FROM settings LIMIT 1");
            self::$cached = self::normalize($row['timezone'] ?? self::DEFAULT);
        } catch (Throwable $e) {
            self::$cached = self::DEFAULT;
        }
        return self::$cached;
    }

    /** Apply settings timezone to this PHP process. */
    public static function apply() {
        self::$cached = null;
        $tz = self::current();
        try {
            date_default_timezone_set($tz);
        } catch (Throwable $e) {
            date_default_timezone_set(self::DEFAULT);
            self::$cached = self::DEFAULT;
        }
        return $tz;
    }
}
