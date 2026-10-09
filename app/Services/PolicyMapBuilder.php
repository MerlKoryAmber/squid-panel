<?php
/**
 * Read-only policy map: graph layout + SVG from spm.db.
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
     *   svg_cascade: string
     * }
     */
    public static function build() {
        $aclRows = Database::fetchAll("SELECT name, type, storage FROM acls ORDER BY name, id");
        $aclIndex = [];
        foreach ($aclRows as $a) {
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
                'action' => strtolower((string)($row['action'] ?? 'deny')),
                'acls' => $acls,
                'enabled' => (int)($row['enabled'] ?? 1) === 1,
                'description' => (string)($row['description'] ?? ''),
            ];
        }

        $peers = [];
        foreach (Database::fetchAll("SELECT * FROM cache_peers ORDER BY id") as $p) {
            $id = (int)$p['id'];
            $label = trim((string)($p['name'] ?? ''));
            if ($label === '') {
                $label = trim((string)($p['hostname'] ?? ''));
            }
            $peers[] = [
                'id' => $id,
                'name' => $label,
                'hostname' => (string)($p['hostname'] ?? ''),
                'http_port' => (int)($p['http_port'] ?? $p['port'] ?? 3128),
                'status' => (string)($p['status'] ?? 'active'),
                'forward_client_ip' => !empty($p['forward_client_ip']),
            ];
        }

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
                'action' => strtolower((string)($r['action'] ?? 'allow')),
                'acls' => array_values($aclNames),
                'negated' => (int)($r['negated'] ?? 0) === 1,
            ];
        }

        $routing = [];
        foreach (Database::fetchAll("SELECT * FROM routing_rules ORDER BY sort_order, id") as $i => $row) {
            $acl = trim((string)($row['acl_name'] ?? ''));
            $routing[] = [
                'id' => (int)($row['id'] ?? 0),
                'order' => $i + 1,
                'directive' => (string)($row['directive'] ?? ''),
                'action' => strtolower((string)($row['action'] ?? 'allow')),
                'acl' => $acl,
                'acls' => $acl !== '' ? [$acl] : [],
                'negated' => (int)($row['negated'] ?? 0) === 1,
            ];
        }

        return [
            'http_access' => $httpAccess,
            'peers' => $peers,
            'peer_access' => $peerAccess,
            'routing' => $routing,
            'acl_index' => $aclIndex,
            'svg_access' => self::svgHttpAccess($httpAccess, $aclIndex),
            'svg_cascade' => self::svgCascade($routing, $peers, $peerAccess, $aclIndex),
        ];
    }

    private static function esc($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function trunc($s, $max = 18) {
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

    /** @param list<array> $httpAccess */
    private static function svgHttpAccess(array $httpAccess, array $aclIndex) {
        $nw = 132;
        $nh = 44;
        $aw = 108;
        $pad = 24;
        $gapAcl = 12;
        $laneGap = 36;
        $xAcl = $pad;
        $xAct = $pad + $nw + 72;

        if (empty($httpAccess)) {
            $w = 420;
            $h = 100;
            return self::svgWrap($w, $h, '<text x="24" y="48" class="pm-svg-muted">No HTTP Access rules</text>');
        }

        $y = $pad;
        $parts = [];
        $parts[] = self::svgDefs();
        $prevActCx = null;
        $prevActCy = null;
        $maxX = $xAct + $aw;
        $maxY = $pad;

        foreach ($httpAccess as $rule) {
            $acls = $rule['acls'];
            if (empty($acls)) {
                $acls = ['(no ACL)'];
            }
            $n = count($acls);
            $blockH = $n * $nh + ($n - 1) * $gapAcl;
            $actY = $y + max(0, ($blockH - $nh) / 2);
            $actCx = $xAct + $aw / 2;
            $actCy = $actY + $nh / 2;

            if ($prevActCx !== null) {
                $parts[] = sprintf(
                    '<path class="pm-svg-edge" d="M %.1f %.1f V %.1f" marker-end="url(#pmArrow)" />',
                    $prevActCx,
                    $prevActCy + $nh / 2,
                    $actY - 4
                );
            }

            $parts[] = sprintf(
                '<text x="%.1f" y="%.1f" class="pm-svg-ord">#%d</text>',
                $xAcl - 4,
                $y + 14,
                (int)$rule['order']
            );

            foreach ($acls as $i => $name) {
                $ny = $y + $i * ($nh + $gapAcl);
                $ghost = ($name === '(no ACL)');
                $type = $ghost ? '' : (string)($aclIndex[$name]['type'] ?? '');
                $parts[] = self::svgNode(
                    $xAcl,
                    $ny,
                    $nw,
                    $nh,
                    self::trunc($name),
                    $type,
                    $ghost ? 'ghost' : 'acl',
                    $ghost ? '' : $name,
                    implode(' ', $rule['acls'])
                );
                $jx = $xAcl + $nw;
                $jy = $ny + $nh / 2;
                $parts[] = sprintf(
                    '<path class="pm-svg-edge" d="M %.1f %.1f C %.1f %.1f, %.1f %.1f, %.1f %.1f" marker-end="url(#pmArrow)" />',
                    $jx,
                    $jy,
                    $jx + 28,
                    $jy,
                    $xAct - 28,
                    $actCy,
                    $xAct,
                    $actCy
                );
            }

            $action = $rule['action'] === 'allow' ? 'allow' : 'deny';
            $sub = !$rule['enabled'] ? 'off' : self::trunc($rule['description'], 14);
            $parts[] = self::svgNode(
                $xAct,
                $actY,
                $aw,
                $nh,
                strtoupper($action),
                $sub,
                $action . (!$rule['enabled'] ? ' disabled' : ''),
                '',
                implode(' ', $rule['acls'])
            );

            $prevActCx = $actCx;
            $prevActCy = $actCy;
            $y = $y + $blockH + $laneGap;
            $maxY = max($maxY, $actY + $nh, $y);
            $maxX = max($maxX, $xAct + $aw);
        }

        return self::svgWrap($maxX + $pad, $maxY + $pad, implode("\n", $parts));
    }

    /**
     * @param list<array> $routing
     * @param list<array> $peers
     * @param list<array> $peerAccess
     */
    private static function svgCascade(array $routing, array $peers, array $peerAccess, array $aclIndex) {
        $nw = 132;
        $nh = 44;
        $pw = 150;
        $ph = 52;
        $pad = 24;
        $rowGap = 28;

        if (empty($routing) && empty($peers)) {
            $w = 520;
            $h = 120;
            $parts = [self::svgDefs()];
            $parts[] = self::svgNode(24, 36, $nw, $nh, 'no cascade', 'in DB', 'ghost', '', '');
            $parts[] = sprintf(
                '<path class="pm-svg-edge" d="M %d %d H %d" marker-end="url(#pmArrow)" />',
                24 + $nw,
                36 + (int)($nh / 2),
                24 + $nw + 80
            );
            $parts[] = self::svgNode(24 + $nw + 80, 36, $pw, $ph, 'DIRECT', 'default', 'direct', '', '');
            return self::svgWrap($w, $h, implode("\n", $parts));
        }

        $parts = [self::svgDefs()];
        $y = $pad;
        $xAcl = $pad;
        $xRight = $pad + $nw + 100;
        $maxX = $xRight + $pw;
        $maxY = $pad;

        if (!empty($routing)) {
            $parts[] = sprintf('<text x="%d" y="%d" class="pm-svg-section">Routing</text>', $pad, $y + 4);
            $y += 18;
            foreach ($routing as $rule) {
                $acls = $rule['acls'];
                if (empty($acls)) {
                    $acls = ['(no ACL)'];
                }
                $name = $acls[0];
                $ghost = ($name === '(no ACL)');
                $type = $ghost ? '' : (string)($aclIndex[$name]['type'] ?? '');
                $isDirect = strtolower($rule['directive']) === 'always_direct';
                $cy = $y + $nh / 2;
                $parts[] = self::svgNode(
                    $xAcl,
                    $y,
                    $nw,
                    $nh,
                    self::trunc($name),
                    $type !== '' ? $type : self::trunc($rule['directive'], 16),
                    $ghost ? 'ghost' : 'acl',
                    $ghost ? '' : $name,
                    implode(' ', $rule['acls'])
                );
                $parts[] = sprintf(
                    '<path class="pm-svg-edge" d="M %.1f %.1f H %.1f" marker-end="url(#pmArrow)" />',
                    $xAcl + $nw,
                    $cy,
                    $xRight
                );
                $parts[] = sprintf(
                    '<text x="%.1f" y="%.1f" class="pm-svg-edge-label" text-anchor="middle">%s</text>',
                    ($xAcl + $nw + $xRight) / 2,
                    $cy - 8,
                    self::esc(self::trunc($rule['directive'], 16))
                );
                if ($isDirect) {
                    $parts[] = self::svgNode($xRight, $y, $pw, $ph, 'DIRECT', '', 'direct', '', implode(' ', $rule['acls']));
                } else {
                    $parts[] = self::svgNode($xRight, $y, $pw, $ph, 'parent peers', 'never_direct', 'peer', '', implode(' ', $rule['acls']));
                }
                $y += max($nh, $ph) + $rowGap;
                $maxY = $y;
                $maxX = max($maxX, $xRight + $pw);
            }
            $y += 8;
        }

        if (!empty($peers)) {
            $parts[] = sprintf('<text x="%d" y="%d" class="pm-svg-section">Peers</text>', $pad, $y + 4);
            $y += 18;
            foreach ($peers as $peer) {
                $pid = (int)$peer['id'];
                $rulesFor = array_values(array_filter($peerAccess, static function ($r) use ($pid) {
                    return (int)$r['peer_id'] === $pid;
                }));
                $peerSub = $peer['hostname'] . ':' . $peer['http_port'];
                if (!empty($peer['forward_client_ip'])) {
                    $peerSub .= ' · XFF';
                }
                $parts[] = self::svgNode(
                    $xRight,
                    $y,
                    $pw,
                    $ph,
                    self::trunc($peer['name'], 16),
                    self::trunc($peerSub, 20),
                    ($peer['status'] ?? '') === 'disabled' ? 'peer disabled' : 'peer',
                    '',
                    ''
                );

                if (empty($rulesFor)) {
                    $parts[] = self::svgNode($xAcl, $y, $nw, $nh, 'no ACL', 'peer_access', 'ghost', '', '');
                    $parts[] = sprintf(
                        '<path class="pm-svg-edge pm-svg-edge-dashed" d="M %.1f %.1f H %.1f" marker-end="url(#pmArrow)" />',
                        $xAcl + $nw,
                        $y + $nh / 2,
                        $xRight
                    );
                    $y += max($nh, $ph) + $rowGap;
                } else {
                    $ry = $y;
                    foreach ($rulesFor as $pr) {
                        $acls = $pr['acls'];
                        if (empty($acls)) {
                            $acls = ['(no ACL)'];
                        }
                        foreach ($acls as $ai => $name) {
                            $ghost = ($name === '(no ACL)');
                            $type = $ghost ? '' : (string)($aclIndex[$name]['type'] ?? '');
                            $ny = $ry + $ai * ($nh + 10);
                            $parts[] = self::svgNode(
                                $xAcl,
                                $ny,
                                $nw,
                                $nh,
                                self::trunc($name),
                                $type !== '' ? $type : $pr['action'],
                                $ghost ? 'ghost' : 'acl',
                                $ghost ? '' : $name,
                                implode(' ', $pr['acls'])
                            );
                            $parts[] = sprintf(
                                '<path class="pm-svg-edge" d="M %.1f %.1f C %.1f %.1f, %.1f %.1f, %.1f %.1f" marker-end="url(#pmArrow)" />',
                                $xAcl + $nw,
                                $ny + $nh / 2,
                                $xAcl + $nw + 30,
                                $ny + $nh / 2,
                                $xRight - 30,
                                $y + $ph / 2,
                                $xRight,
                                $y + $ph / 2
                            );
                        }
                        $ry += max(count($acls), 1) * ($nh + 10) + 8;
                    }
                    $y = max($y + $ph, $ry) + $rowGap;
                }
                $maxY = $y;
                $maxX = max($maxX, $xRight + $pw);
            }
        }

        return self::svgWrap($maxX + $pad, $maxY + $pad, implode("\n", $parts));
    }

    private static function svgDefs() {
        return '<defs>
  <marker id="pmArrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
    <path d="M 0 0 L 10 5 L 0 10 z" class="pm-svg-marker" />
  </marker>
</defs>';
    }

    private static function svgNode($x, $y, $w, $h, $title, $sub, $kind, $aclName, $aclsAttr) {
        $cls = 'pm-svg-node pm-svg-node-' . preg_replace('/[^a-z-]+/', '-', strtolower(trim($kind)));
        $dataAcl = $aclName !== '' ? ' data-acl="' . self::esc($aclName) . '"' : '';
        $dataAcls = $aclsAttr !== '' ? ' data-acls="' . self::esc($aclsAttr) . '"' : '';
        $role = $aclName !== '' ? ' role="button" tabindex="0"' : '';
        $out = sprintf('<g class="%s"%s%s%s transform="translate(%.1f,%.1f)">', $cls, $dataAcl, $dataAcls, $role, $x, $y);
        $out .= sprintf('<rect width="%.1f" height="%.1f" rx="8" ry="8" />', $w, $h);
        $out .= sprintf('<text x="10" y="18" class="pm-svg-title">%s</text>', self::esc($title));
        if ($sub !== '') {
            $out .= sprintf('<text x="10" y="34" class="pm-svg-sub">%s</text>', self::esc($sub));
        }
        $out .= '</g>';
        return $out;
    }

    private static function svgWrap($w, $h, $inner) {
        $w = max(320, (int)ceil($w));
        $h = max(80, (int)ceil($h));
        return sprintf(
            '<svg class="pm-svg" viewBox="0 0 %d %d" width="100%%" height="%d" xmlns="http://www.w3.org/2000/svg" aria-hidden="false">%s</svg>',
            $w,
            $h,
            min(900, $h),
            $inner
        );
    }
}
