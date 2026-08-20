<?php
class PolicyAclKind {
    public static function kind($name, $type, $storage = 'inline') {
        $name = (string)$name;
        $type = strtolower(trim((string)$type));
        $storage = (string)$storage;
        if ($name !== '' && strpos($name, 'ad_') === 0) {
            return 'from';
        }
        $fromTypes = [
            'src' => true,
            'srcdomain' => true,
            'srcdom_regex' => true,
            'arp' => true,
            'proxy_auth' => true,
            'ext_user' => true,
        ];
        if (isset($fromTypes[$type])) {
            return 'from';
        }
        $toTypes = [
            'dstdomain' => true,
            'dstdom_regex' => true,
            'dst' => true,
            'url_regex' => true,
            'urlpath_regex' => true,
        ];
        if (isset($toTypes[$type])) {
            return 'to';
        }
        if ($storage === 'file' && ($type === 'dstdomain' || $type === 'url_regex' || $type === 'dst')) {
            return 'to';
        }
        if ($type === 'external') {
            return 'from';
        }
        return 'other';
    }

    public static function catalogByName() {
        $out = [];
        foreach (Database::fetchAll("SELECT name, type, storage, description FROM acls") as $row) {
            $out[$row['name']] = $row;
        }
        return $out;
    }

    public static function analyze(array $tokens, array $catalog) {
        $from = [];
        $to = [];
        $other = [];
        $simple = true;
        foreach ($tokens as $raw) {
            $raw = trim((string)$raw);
            if ($raw === '') {
                continue;
            }
            if (isset($raw[0]) && $raw[0] === '!') {
                $simple = false;
                $other[] = $raw;
                continue;
            }
            $meta = $catalog[$raw] ?? null;
            if (!$meta) {
                $simple = false;
                $other[] = $raw;
                continue;
            }
            $kind = self::kind($meta['name'], $meta['type'], $meta['storage'] ?? 'inline');
            if ($kind === 'from') {
                $from[] = $raw;
            } elseif ($kind === 'to') {
                $to[] = $raw;
            } else {
                $simple = false;
                $other[] = $raw;
            }
        }
        if ($from === [] && $to === []) {
            $simple = false;
        }
        return [
            'simple' => $simple && $other === [],
            'from' => $from,
            'to' => $to,
            'other' => $other,
        ];
    }

    public static function tokensFromJson($json) {
        $decoded = json_decode((string)$json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $t) {
            $t = trim((string)$t);
            if ($t !== '') {
                $out[] = $t;
            }
        }
        return $out;
    }

    public static function lists($kind, array $catalog) {
        $out = [];
        foreach ($catalog as $name => $meta) {
            if (self::kind($meta['name'], $meta['type'], $meta['storage'] ?? 'inline') === $kind) {
                $out[] = $meta;
            }
        }
        usort($out, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        return $out;
    }

    public static function label(array $meta, $withType = false) {
        $name = trim((string)($meta['name'] ?? ''));
        $d = trim((string)($meta['description'] ?? ''));
        $generic = ($d === '' || strcasecmp($d, 'Imported from squid.conf') === 0);
        $text = $name;
        if (!$generic && $d !== $name) {
            $text = $name !== '' ? $name . ' — ' . $d : $d;
        }
        if ($withType) {
            $type = trim((string)($meta['type'] ?? ''));
            if ($type !== '') {
                $text .= ' (' . $type . ')';
            }
        }
        return $text !== '' ? $text : $name;
    }

    public static function ruleTitle(array $rule) {
        $d = trim((string)($rule['description'] ?? ''));
        if ($d === '' || strcasecmp($d, 'Imported from squid.conf') === 0) {
            return 'Rule #' . (int)($rule['id'] ?? 0);
        }
        return $d;
    }
}
