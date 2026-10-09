<?php
/**
 * Read-only policy map: compact SVG graph from spm.db.
 * Access: AND-group → ALLOW/DENY; 1–2 columns by width.
 * Cascade: only real paths (never_direct / always_direct / peer_access allow).
 */
class PolicyMapBuilder {
    /**
     * @return array{
     *   http_access: list<array>,
     *   peers: list<array>,
     *   peer_access: list<array>,
     *   routing: list<array>,
     *   acl_index: array<string, array>,
     *   svg_access: string,
     *   svg_cascade: string,
     *   access_meta: array{cols:int,width:int,height:int},
     *   cascade_meta: array{width:int,height:int}
     * }
     */
    public static function build() {
        $aclIndex = self::loadAclIndex();
        $httpAccess = self::loadHttpAccess();
        $peers = self::loadPeers();
        $peerAccess = self::loadPeerAccess();
        $routing = self::loadRouting();

        $access1 = self::svgHttpAccess($httpAccess, $aclIndex, 1);
        $access2 = self::svgHttpAccess($httpAccess, $aclIndex, 2);
        $cascadeSvg = self::svgCascade($routing, $peers, $peerAccess, $aclIndex);

        return [
            'http_access' => $httpAccess,
            'peers' => $peers,
            'peer_access' => $peerAccess,
            'routing' => $routing,
            'acl_index' => $aclIndex,
            'svg_access' => $access2['svg'],
            'svg_access_1' => $access1['svg'],
            'svg_access_2' => $access2['svg'],
            'svg_cascade' => $cascadeSvg['svg'],
            'access_meta' => [
                'cols' => $access2['cols'],
                'width' => $access2['width'],
                'height' => $access2['height'],
            ],
            'cascade_meta' => [
                'width' => $cascadeSvg['width'],
                'height' => $cascadeSvg['height'],
            ],
        ];
    }

    private static function loadAclIndex() {
        $aclIndex = [];
        foreach (Database::fetchAll("SELECT name, type, storage FROM acls ORDER BY name, id") as $a) {
            $name = (string)($a['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $aclIndex[$name] = [
                'name' => $name,
                'type' => (string)($a['type'] ?? ''),
                'storage' => (string)($a['storage'] ?? 'inline'),
            ];
        }
        return $aclIndex;
    }

    private static function loadHttpAccess() {
        $httpAccess = [];
        foreach (Database::fetchAll("SELECT * FROM http_access_rules ORDER BY sort_order, id") as $i => $row) {
            $acls = json_decode($row['acls'] ?? '[]', true);
            if (!is_array($acls)) {
                $acls = [];
            }
            $acls = array_values(array_filter(array_map('strval', $acls), static function ($n) {
                return $n !== '';
            }));
            $httpAccess[] = [
                'id' => (int)($row['id'] ?? 0),
                'order' => $i + 1,
                'action' => strtolower((string)($row['action'] ?? 'deny')) === 'allow' ? 'allow' : 'deny',
                'acls' => $acls,
                'enabled' => (int)($row['enabled'] ?? 1) === 1,
                'description' => (string)($row['description'] ?? ''),
            ];
        }
        return $httpAccess;
    }

    private static function loadPeers() {
        $peers = [];
        foreach (Database::fetchAll("SELECT * FROM cache_peers ORDER BY id") as $p) {
            $label = trim((string)($p['name'] ?? ''));
            if ($label === '') {
                $label = trim((string)($p['hostname'] ?? ''));
            }
            $peers[] = [
                'id' => (int)$p['id'],
                'name' => $label,
                'hostname' => (string)($p['hostname'] ?? ''),
                'http_port' => (int)($p['http_port'] ?? $p['port'] ?? 3128),
                'status' => (string)($p['status'] ?? 'active'),
                'forward_client_ip' => !empty($p['forward_client_ip']),
            ];
        }
        return $peers;
    }

    private static function loadPeerAccess() {
        $peerAccess = [];
        foreach (Database::fetchAll(
            "SELECT r.*, p.name AS peer_name, p.hostname AS peer_hostname
             FROM cache_peer_access_rules r
             LEFT JOIN cache_peers p ON p.id = r.peer_id
             ORDER BY r.peer_id, r.sort_order, r.id"
        ) as $r) {
            $entries = trim((string)(($r['acl_entries'] ?? '') !== '' ? $r['acl_entries'] : ($r['acl_name'] ?? '')));
            $aclNames = preg_split('/\s+/', $entries, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $peerLabel = trim((string)($r['peer_name'] ?? ''));
            if ($peerLabel === '') {
                $peerLabel = trim((string)($r['peer_hostname'] ?? $r['hostname'] ?? ''));
            }
            $peerAccess[] = [
                'id' => (int)($r['id'] ?? 0),
                'peer_id' => (int)($r['peer_id'] ?? 0),
                'peer_label' => $peerLabel,
                'action' => strtolower((string)($r['action'] ?? 'allow')) === 'allow' ? 'allow' : 'deny',
                'acls' => array_values($aclNames),
            ];
        }
        return $peerAccess;
    }

    private static function loadRouting() {
        $routing = [];
        foreach (Database::fetchAll("SELECT * FROM routing_rules ORDER BY sort_order, id") as $i => $row) {
            $acl = trim((string)($row['acl_name'] ?? ''));
            $routing[] = [
                'id' => (int)($row['id'] ?? 0),
                'order' => $i + 1,
                'directive' => (string)($row['directive'] ?? ''),
                'action' => strtolower((string)($row['action'] ?? 'allow')),
                'acls' => $acl !== '' ? [$acl] : [],
            ];
        }
        return $routing;
    }

    private static function esc($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function trunc($s, $max = 22) {
        $s = (string)$s;
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($s) <= $max) {
                return $s;
            }
            return mb_substr($s, 0, $max - 1) . '…';
        }
        if (strlen($s) <= $max) {
            return $s;
        }
        return substr($s, 0, $max - 1) . '~';
    }

    /**
     * Access A: each rule = AND chip-row → one arrow → ALLOW/DENY.
     * Packed in $maxCols columns (responsive CSS can hide/show alternate svgs later;
     * we emit one SVG with data-cols and compact cell size).
     *
     * @param list<array> $httpAccess
     * @return array{svg:string,cols:int,width:int,height:int}
     */
    private static function svgHttpAccess(array $httpAccess, array $aclIndex, $maxCols = 2) {
        // Show enabled first in visual order; keep original order numbers.
        $rules = array_values(array_filter($httpAccess, static function ($r) {
            return !empty($r['enabled']);
        }));
        // Disabled appended faintly at end (optional) — skip for compact overview.
        if (empty($rules)) {
            $rules = $httpAccess;
        }

        $n = count($rules);
        if ($n === 0) {
            $w = 360;
            $h = 72;
            $svg = self::svgWrap($w, $h, self::svgDefs() . '<text x="16" y="40" class="pm-svg-muted">No HTTP Access rules</text>', 'pm-svg pm-svg-access');
            return ['svg' => $svg, 'cols' => 1, 'width' => $w, 'height' => $h];
        }

        $cols = $n >= 4 ? min(2, (int)$maxCols) : 1;
        $chipH = 42; // room for ACL name + type (src) without overlap
        $chipGap = 6;
        $rowPad = 10;
        $cellPadX = 12;
        $cellPadY = 10;
        $actionW = 86;
        $actionH = 42;
        $arrow = 36;
        $colGap = 28;
        $rowGap = 18;

        // Pre-measure each rule block height/width
        $blocks = [];
        foreach ($rules as $rule) {
            $acls = $rule['acls'];
            if (empty($acls)) {
                $acls = ['(no ACL)'];
            }
            $chipsW = 0;
            $chipWidths = [];
            foreach ($acls as $name) {
                $cw = max(72, min(160, 10 + strlen($name) * 7.2));
                $chipWidths[] = $cw;
                $chipsW += $cw + $chipGap;
            }
            $chipsW -= $chipGap;
            // AND labels between chips
            $chipsW += max(0, count($acls) - 1) * 14;
            $blockW = $chipsW + $arrow + $actionW;
            $blockH = max($actionH, $chipH) + 18; // ord label
            $blocks[] = [
                'rule' => $rule,
                'acls' => $acls,
                'chip_widths' => $chipWidths,
                'w' => $blockW,
                'h' => $blockH,
                'chips_w' => $chipsW,
            ];
        }

        $colW = [];
        for ($c = 0; $c < $cols; $c++) {
            $colW[$c] = 0;
        }
        foreach ($blocks as $i => $b) {
            $c = $i % $cols;
            $colW[$c] = max($colW[$c], $b['w']);
        }

        $rows = (int)ceil($n / $cols);
        $rowH = [];
        for ($r = 0; $r < $rows; $r++) {
            $rowH[$r] = 0;
            for ($c = 0; $c < $cols; $c++) {
                $i = $r * $cols + $c;
                if (!isset($blocks[$i])) {
                    continue;
                }
                $rowH[$r] = max($rowH[$r], $blocks[$i]['h']);
            }
        }

        $width = $cellPadX;
        for ($c = 0; $c < $cols; $c++) {
            $width += $colW[$c] + ($c > 0 ? $colGap : 0);
        }
        $width += $cellPadX;

        $height = $cellPadY;
        for ($r = 0; $r < $rows; $r++) {
            $height += $rowH[$r] + ($r > 0 ? $rowGap : 0);
        }
        $height += $cellPadY + 8;

        $parts = [self::svgDefs()];
        $yBase = $cellPadY;

        for ($r = 0; $r < $rows; $r++) {
            $xBase = $cellPadX;
            for ($c = 0; $c < $cols; $c++) {
                $i = $r * $cols + $c;
                if (!isset($blocks[$i])) {
                    break;
                }
                $b = $blocks[$i];
                $rule = $b['rule'];
                $x = $xBase;
                $y = $yBase + 16;

                $parts[] = sprintf(
                    '<text x="%.1f" y="%.1f" class="pm-svg-ord">#%d · %s</text>',
                    $x,
                    $yBase + 12,
                    (int)$rule['order'],
                    self::esc(strtoupper($rule['action']))
                );

                $aclsAttr = implode(' ', $rule['acls']);
                $chipX = $x;
                $chipY = $y + max(0, ($actionH - $chipH) / 2);
                foreach ($b['acls'] as $j => $name) {
                    if ($j > 0) {
                        $parts[] = sprintf(
                            '<text x="%.1f" y="%.1f" class="pm-svg-and" text-anchor="middle">∧</text>',
                            $chipX + 7,
                            $chipY + $chipH / 2 + 4
                        );
                        $chipX += 14;
                    }
                    $cw = $b['chip_widths'][$j];
                    $ghost = ($name === '(no ACL)');
                    $type = $ghost ? '' : (string)($aclIndex[$name]['type'] ?? '');
                    $parts[] = self::svgChip(
                        $chipX,
                        $chipY,
                        $cw,
                        $chipH,
                        self::trunc($name, 18),
                        $type,
                        $ghost ? 'ghost' : 'acl',
                        $ghost ? '' : $name,
                        $aclsAttr
                    );
                    $chipX += $cw + $chipGap;
                }

                $actX = $x + $b['chips_w'] + $arrow;
                $actY = $y;
                $parts[] = sprintf(
                    '<path class="pm-svg-edge" d="M %.1f %.1f H %.1f" marker-end="url(#pmArrow)" />',
                    $x + $b['chips_w'] + 2,
                    $chipY + $chipH / 2,
                    $actX - 2
                );

                $sub = $rule['description'] !== '' ? self::trunc($rule['description'], 12) : '';
                $parts[] = self::svgChip(
                    $actX,
                    $actY,
                    $actionW,
                    $actionH,
                    strtoupper($rule['action']),
                    $sub,
                    $rule['action'],
                    '',
                    $aclsAttr
                );

                // next-match hint to next rule in reading order
                $next = $i + 1;
                if ($next < $n) {
                    $nr = (int)floor($next / $cols);
                    $nc = $next % $cols;
                    if ($nr === $r && $nc === $c + 1) {
                        // same row → horizontal next
                        $parts[] = sprintf(
                            '<path class="pm-svg-edge pm-svg-edge-next" d="M %.1f %.1f H %.1f" marker-end="url(#pmArrowMuted)" />',
                            $actX + $actionW + 2,
                            $actY + $actionH / 2,
                            $xBase + $colW[$c] + $colGap - 8
                        );
                    } elseif ($c === $cols - 1 || $next % $cols === 0) {
                        // down to next row
                        $parts[] = sprintf(
                            '<path class="pm-svg-edge pm-svg-edge-next" d="M %.1f %.1f V %.1f" marker-end="url(#pmArrowMuted)" />',
                            $actX + $actionW / 2,
                            $actY + $actionH + 2,
                            $yBase + $rowH[$r] + $rowGap - 4
                        );
                    }
                }

                $xBase += $colW[$c] + $colGap;
            }
            $yBase += $rowH[$r] + $rowGap;
        }

        $parts[] = '<text x="12" y="' . ($height - 4) . '" class="pm-svg-caption">AND inside a rule · first match wins (reading order)</text>';

        $svg = self::svgWrap($width, $height, implode("\n", $parts), 'pm-svg pm-svg-access');
        return ['svg' => $svg, 'cols' => $cols, 'width' => (int)$width, 'height' => (int)$height];
    }

    /**
     * Cascade 1A: only ACLs that actually route; only peers with allow; deny → badge only.
     *
     * @param list<array> $routing
     * @param list<array> $peers
     * @param list<array> $peerAccess
     * @return array{svg:string,width:int,height:int}
     */
    private static function svgCascade(array $routing, array $peers, array $peerAccess, array $aclIndex) {
        $peerById = [];
        foreach ($peers as $p) {
            if (($p['status'] ?? '') === 'disabled') {
                continue;
            }
            $peerById[(int)$p['id']] = $p;
        }

        // ACL → destinations from routing
        $edges = []; // list of [acl, kind, label, sub]
        foreach ($routing as $rule) {
            if (($rule['action'] ?? '') !== 'allow' && strtolower((string)($rule['action'] ?? '')) !== 'allow') {
                // still show allow-style routing; skip weird
            }
            $dir = strtolower((string)$rule['directive']);
            foreach ($rule['acls'] as $acl) {
                if ($acl === '') {
                    continue;
                }
                if ($dir === 'always_direct') {
                    $edges[] = ['acl' => $acl, 'kind' => 'direct', 'label' => 'DIRECT', 'sub' => 'always_direct'];
                } elseif ($dir === 'never_direct') {
                    // never_direct alone ≠ which peer; peer chosen by peer_access — mark as "force parent"
                    $edges[] = ['acl' => $acl, 'kind' => 'force_parent', 'label' => 'parent', 'sub' => 'never_direct'];
                }
            }
        }

        // peer_access allow only → concrete peer
        $denyCountByPeer = [];
        $allowByPeer = []; // peer_id => list of acl names (unique)
        foreach ($peerAccess as $r) {
            $pid = (int)$r['peer_id'];
            if (!isset($peerById[$pid])) {
                continue;
            }
            if ($r['action'] === 'deny') {
                $denyCountByPeer[$pid] = ($denyCountByPeer[$pid] ?? 0) + 1;
                continue;
            }
            if ($r['action'] !== 'allow') {
                continue;
            }
            if (!isset($allowByPeer[$pid])) {
                $allowByPeer[$pid] = [];
            }
            foreach ($r['acls'] as $acl) {
                if ($acl === '') {
                    continue;
                }
                $allowByPeer[$pid][$acl] = true;
            }
        }

        $paths = []; // unique acl|destKey
        $seen = [];
        foreach ($allowByPeer as $pid => $aclSet) {
            $p = $peerById[$pid];
            $destKey = 'peer:' . $pid;
            foreach (array_keys($aclSet) as $acl) {
                $k = $acl . '|' . $destKey;
                if (isset($seen[$k])) {
                    continue;
                }
                $seen[$k] = true;
                $paths[] = [
                    'acl' => $acl,
                    'kind' => 'peer',
                    'peer_id' => $pid,
                    'label' => $p['name'],
                    'sub' => $p['hostname'] . ':' . $p['http_port'],
                    'denies' => (int)($denyCountByPeer[$pid] ?? 0),
                    'xff' => !empty($p['forward_client_ip']),
                ];
            }
        }

        // ACLs with never_direct but no peer allow — still show → parent (unresolved)
        foreach ($edges as $e) {
            if ($e['kind'] !== 'force_parent') {
                continue;
            }
            $acl = $e['acl'];
            $hasPeer = false;
            foreach ($paths as $p) {
                if ($p['acl'] === $acl) {
                    $hasPeer = true;
                    break;
                }
            }
            if (!$hasPeer) {
                $k = $acl . '|parent';
                if (!isset($seen[$k])) {
                    $seen[$k] = true;
                    $paths[] = [
                        'acl' => $acl,
                        'kind' => 'parent',
                        'label' => 'parent peers',
                        'sub' => 'never_direct (no peer_access allow)',
                        'denies' => 0,
                        'xff' => false,
                    ];
                }
            }
        }

        foreach ($edges as $e) {
            if ($e['kind'] !== 'direct') {
                continue;
            }
            $k = $e['acl'] . '|direct';
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $paths[] = [
                'acl' => $e['acl'],
                'kind' => 'direct',
                'label' => 'DIRECT',
                'sub' => 'always_direct',
                'denies' => 0,
                'xff' => false,
            ];
        }

        if (empty($paths)) {
            $w = 420;
            $h = 88;
            $parts = [self::svgDefs()];
            $parts[] = self::svgChip(16, 24, 120, 36, 'no cascade', 'paths', 'ghost', '', '');
            $parts[] = sprintf('<path class="pm-svg-edge" d="M %d %d H %d" marker-end="url(#pmArrow)" />', 140, 42, 200);
            $parts[] = self::svgChip(200, 24, 100, 36, 'DIRECT', 'default', 'direct', '', '');
            $svg = self::svgWrap($w, $h, implode("\n", $parts), 'pm-svg pm-svg-cascade');
            return ['svg' => $svg, 'width' => $w, 'height' => $h];
        }

        // Group paths by destination for compact right column
        $byDest = [];
        foreach ($paths as $p) {
            $dk = $p['kind'] . ':' . $p['label'];
            if (!isset($byDest[$dk])) {
                $byDest[$dk] = ['meta' => $p, 'acls' => []];
            }
            $byDest[$dk]['acls'][$p['acl']] = true;
        }

        $nw = 148;
        $nh = 42;
        $pw = 156;
        $ph = 48;
        $pad = 16;
        $gap = 14;
        $xAcl = $pad;
        $xPeer = $pad + $nw + 70;

        $destList = array_values($byDest);
        $y = $pad + 8;
        $parts = [self::svgDefs()];
        $parts[] = '<text x="' . $pad . '" y="' . ($pad) . '" class="pm-svg-caption">Only ACLs that enter cascade · peer_access allow</text>';
        $y = $pad + 14;

        $maxY = $y;
        $peerColorIdx = 0;
        foreach ($destList as $dest) {
            $acls = array_keys($dest['acls']);
            sort($acls);
            $meta = $dest['meta'];
            $blockH = max($ph, count($acls) * ($nh + 8) - 8);
            $peerY = $y + max(0, ($blockH - $ph) / 2);
            $peerCy = $peerY + $ph / 2;

            $sub = $meta['sub'];
            if (!empty($meta['xff'])) {
                $sub .= ' · XFF';
            }
            if (!empty($meta['denies'])) {
                $sub .= ' · denies:' . (int)$meta['denies'];
            }
            if ($meta['kind'] === 'direct') {
                $kind = 'direct';
            } elseif ($meta['kind'] === 'parent') {
                $kind = 'parent';
            } else {
                $kind = ($peerColorIdx % 2 === 0) ? 'peer-silver' : 'peer-bronze';
                $peerColorIdx++;
            }
            $parts[] = self::svgChip($xPeer, $peerY, $pw, $ph, self::trunc($meta['label'], 16), self::trunc($sub, 22), $kind, '', implode(' ', $acls));

            foreach ($acls as $i => $acl) {
                $ay = $y + $i * ($nh + 8);
                $type = (string)($aclIndex[$acl]['type'] ?? '');
                $parts[] = self::svgChip($xAcl, $ay, $nw, $nh, self::trunc($acl, 16), $type, 'acl', $acl, $acl);
                $parts[] = sprintf(
                    '<path class="pm-svg-edge" d="M %.1f %.1f C %.1f %.1f, %.1f %.1f, %.1f %.1f" marker-end="url(#pmArrow)" />',
                    $xAcl + $nw,
                    $ay + $nh / 2,
                    $xAcl + $nw + 24,
                    $ay + $nh / 2,
                    $xPeer - 24,
                    $peerCy,
                    $xPeer,
                    $peerCy
                );
            }

            $y += $blockH + $gap;
            $maxY = $y;
        }

        $width = $xPeer + $pw + $pad;
        $height = $maxY + $pad;
        $svg = self::svgWrap($width, $height, implode("\n", $parts), 'pm-svg pm-svg-cascade');
        return ['svg' => $svg, 'width' => (int)$width, 'height' => (int)$height];
    }

    private static function svgDefs() {
        return '<defs>
  <marker id="pmArrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
    <path d="M 0 0 L 10 5 L 0 10 z" class="pm-svg-marker" />
  </marker>
  <marker id="pmArrowMuted" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
    <path d="M 0 0 L 10 5 L 0 10 z" class="pm-svg-marker-muted" />
  </marker>
</defs>';
    }

    private static function svgChip($x, $y, $w, $h, $title, $sub, $kind, $aclName, $aclsAttr) {
        $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower(trim((string)$kind)));
        $cls = 'pm-svg-node pm-svg-node-' . $slug;
        if (strpos($kind, 'disabled') !== false) {
            $cls .= ' pm-svg-node-disabled';
        }
        $dataAcl = $aclName !== '' ? ' data-acl="' . self::esc($aclName) . '"' : '';
        $dataAcls = $aclsAttr !== '' ? ' data-acls="' . self::esc($aclsAttr) . '"' : '';
        $role = $aclName !== '' ? ' role="button" tabindex="0"' : '';
        $out = sprintf('<g class="%s"%s%s%s transform="translate(%.1f,%.1f)">', $cls, $dataAcl, $dataAcls, $role, $x, $y);
        $out .= sprintf('<rect width="%.1f" height="%.1f" rx="7" ry="7" />', $w, $h);
        // Title on first line, type/sub on second — never overlap.
        if ($sub !== '') {
            $out .= sprintf('<text x="10" y="17" class="pm-svg-title">%s</text>', self::esc($title));
            $out .= sprintf('<text x="10" y="33" class="pm-svg-sub">%s</text>', self::esc($sub));
        } else {
            $out .= sprintf('<text x="10" y="%.1f" class="pm-svg-title">%s</text>', $h / 2 + 4, self::esc($title));
        }
        $out .= '</g>';
        return $out;
    }

    private static function svgWrap($w, $h, $inner, $class = 'pm-svg') {
        $w = max(280, (int)ceil($w));
        $h = max(64, (int)ceil($h));
        return sprintf(
            '<svg class="%s" viewBox="0 0 %d %d" width="%d" height="%d" xmlns="http://www.w3.org/2000/svg">%s</svg>',
            self::esc($class),
            $w,
            $h,
            $w,
            $h,
            $inner
        );
    }
}
