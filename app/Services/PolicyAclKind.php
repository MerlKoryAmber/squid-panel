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
        foreach (Database::fetchAll("SELECT name, type, storage, description, entries FROM acls") as $row) {
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

    /** Same column texts as simple rules; extra tokens (port, !acl) stay in from/to, not a Complex badge. */
    public static function columnLabels(array $parsed, array $catalog) {
        $from = [];
        foreach ($parsed['from'] ?? [] as $name) {
            $from[] = self::tokenLabel((string)$name, $catalog);
        }
        $to = [];
        foreach ($parsed['to'] ?? [] as $name) {
            $to[] = self::tokenLabel((string)$name, $catalog);
        }
        foreach ($parsed['other'] ?? [] as $raw) {
            $raw = (string)$raw;
            $bare = (isset($raw[0]) && $raw[0] === '!') ? substr($raw, 1) : $raw;
            $meta = $catalog[$bare] ?? null;
            $kind = $meta
                ? self::kind($meta['name'], $meta['type'], $meta['storage'] ?? 'inline')
                : 'other';
            $label = self::tokenLabel($raw, $catalog);
            if ($kind === 'from') {
                $from[] = $label;
            } else {
                $to[] = $label;
            }
        }
        return ['from' => $from, 'to' => $to];
    }

    public static function tokenLabel($raw, array $catalog) {
        $raw = trim((string)$raw);
        $neg = ($raw !== '' && $raw[0] === '!');
        $name = $neg ? substr($raw, 1) : $raw;
        $meta = $catalog[$name] ?? ['name' => $name];
        $text = self::labelWithPreview($meta);
        if ($neg) {
            $text = 'except ' . $text;
        }
        return $text;
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

    public static function memberPreview(array $meta, $limit = 3) {
        $limit = (int)$limit;
        if ($limit < 1) {
            $limit = 3;
        }
        $name = (string)($meta['name'] ?? '');
        $type = strtolower(trim((string)($meta['type'] ?? '')));
        if (($name !== '' && strpos($name, 'ad_') === 0) || $type === 'external') {
            return ['samples' => [], 'total' => 0, 'note' => 'AD group · live LDAP'];
        }
        if (($meta['storage'] ?? 'inline') === 'file' && $name !== '' && class_exists('AclListFile')) {
            $file = AclListFile::previewWorkFile($name, $limit);
            return [
                'samples' => $file['samples'],
                'total' => (int)$file['total'],
                'note' => '',
            ];
        }
        $vals = json_decode((string)($meta['entries'] ?? '[]'), true);
        if (!is_array($vals)) {
            $vals = [];
        }
        $clean = [];
        foreach ($vals as $v) {
            $v = trim((string)$v);
            if ($v === '' || (class_exists('AclListFile') && AclListFile::looksLikeFileRef($v))) {
                continue;
            }
            $clean[] = $v;
        }
        return [
            'samples' => array_slice($clean, 0, $limit),
            'total' => count($clean),
            'note' => '',
        ];
    }

    public static function labelWithPreview(array $meta, $limit = 3) {
        $name = self::label($meta, false);
        $p = self::memberPreview($meta, $limit);
        if ($p['note'] !== '') {
            return $name . ' — ' . $p['note'];
        }
        if ($p['samples'] === []) {
            return $name;
        }
        $bits = [];
        foreach ($p['samples'] as $s) {
            if (strlen($s) > 32) {
                $s = substr($s, 0, 29) . '…';
            }
            $bits[] = $s;
        }
        $text = $name . ' — ' . implode(', ', $bits);
        $more = $p['total'] - count($p['samples']);
        if ($more > 0) {
            $text .= ' (+' . $more . ')';
        }
        return $text;
    }
}
