<?php
/**
 * Rewrite ACL name tokens in stored rule strings (space list or JSON list).
 * Honours Squid "!name" negation. Does not match a prefix of another name.
 */
class AclNameRefs {
    public static function rewriteBare($token, $old, $new) {
        $token = (string)$token;
        $old = (string)$old;
        $new = (string)$new;
        if ($token === '' || $old === '' || $old === $new) {
            return $token;
        }
        $bang = '';
        $core = $token;
        if ($core !== '' && $core[0] === '!') {
            $bang = '!';
            $core = substr($core, 1);
        }
        if ($core === $old) {
            return $bang . $new;
        }
        return $token;
    }

    public static function rewriteSpaceList($text, $old, $new) {
        $text = trim((string)$text);
        if ($text === '' || $old === $new) {
            return $text;
        }
        $parts = preg_split('/\s+/', $text);
        $out = [];
        foreach ($parts as $p) {
            if ($p === '') {
                continue;
            }
            $out[] = self::rewriteBare($p, $old, $new);
        }
        return implode(' ', $out);
    }

    public static function rewriteJsonList($json, $old, $new) {
        $arr = json_decode((string)$json, true);
        if (!is_array($arr) || $old === $new) {
            return (string)$json;
        }
        foreach ($arr as $i => $item) {
            if (is_array($item)) {
                continue;
            }
            $arr[$i] = self::rewriteBare((string)$item, $old, $new);
        }
        return json_encode(array_values($arr));
    }
}
