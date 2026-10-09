<?php
/** @var list<array> $httpAccess */
/** @var list<array> $peers */
/** @var list<array> $peerAccess */
/** @var list<array> $routing */
/** @var array<string, array> $aclIndex */

$aclType = static function ($name) use ($aclIndex) {
    return (string)($aclIndex[$name]['type'] ?? '');
};

$renderAclNode = static function ($name) use ($aclType) {
    $t = $aclType($name);
    $safe = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $title = htmlspecialchars($t !== '' ? $t : 'ACL', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<button type="button" class="pm-node pm-node-acl" data-acl="' . $safe . '" title="' . $title . '">';
    echo '<span class="pm-node-title">' . $safe . '</span>';
    if ($t !== '') {
        echo '<span class="pm-node-sub">' . htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
    }
    echo '</button>';
};
?>
<div class="page-header">
    <h2>Policy map</h2>
    <p class="pm-map-lead">Read-only schematic from the panel database. Squid matches <strong>top → bottom</strong>. Click an ACL node to highlight it on both layers.</p>
</div>

<div class="pm-map-legend" aria-hidden="true">
    <span class="pm-map-leg pm-map-leg-acl">ACL</span>
    <span class="pm-map-leg pm-map-leg-allow">allow</span>
    <span class="pm-map-leg pm-map-leg-deny">deny</span>
    <span class="pm-map-leg pm-map-leg-peer">peer</span>
    <span class="pm-map-leg pm-map-leg-direct">DIRECT</span>
    <span class="pm-map-leg pm-map-leg-disabled">disabled</span>
</div>

<section class="card pm-map-layer" id="pm-layer-access">
    <div class="card-header"><h3>HTTP Access <span class="pm-map-hint">flow top → bottom</span></h3></div>
    <div class="card-body">
        <?php if (empty($httpAccess)): ?>
            <p class="pm-map-empty">No HTTP Access rules in the database.</p>
        <?php else: ?>
            <div class="pm-schematic pm-schematic-access">
                <?php
                $n = count($httpAccess);
                foreach ($httpAccess as $i => $rule):
                    $action = $rule['action'] === 'allow' ? 'allow' : 'deny';
                    $aclAttr = htmlspecialchars(implode(' ', $rule['acls']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $stepCls = 'pm-step pm-step-' . $action;
                    if (!$rule['enabled']) {
                        $stepCls .= ' pm-step-disabled';
                    }
                ?>
                <div class="<?= $stepCls ?>" data-acls="<?= $aclAttr ?>">
                    <div class="pm-step-ord">#<?= (int)$rule['order'] ?></div>
                    <div class="pm-step-row">
                        <div class="pm-step-acls">
                            <?php
                            $acls = $rule['acls'];
                            if (empty($acls)) {
                                echo '<span class="pm-node pm-node-ghost">(no ACL)</span>';
                            } else {
                                foreach ($acls as $j => $an) {
                                    if ($j > 0) {
                                        echo '<span class="pm-join" aria-hidden="true">+</span>';
                                    }
                                    $renderAclNode($an);
                                }
                            }
                            ?>
                        </div>
                        <span class="pm-arrow-h" aria-hidden="true"></span>
                        <div class="pm-node pm-node-action pm-node-<?= $action ?>">
                            <span class="pm-node-title"><?= htmlspecialchars(strtoupper($action), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                            <?php if (!$rule['enabled']): ?>
                                <span class="pm-node-sub">off</span>
                            <?php elseif ($rule['description'] !== ''): ?>
                                <span class="pm-node-sub"><?= htmlspecialchars($rule['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php if ($i < $n - 1): ?>
                    <div class="pm-arrow-v" aria-hidden="true"></div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="card pm-map-layer" id="pm-layer-cascade">
    <div class="card-header"><h3>Cascade <span class="pm-map-hint">ACL → peer / DIRECT</span></h3></div>
    <div class="card-body">
        <?php
        $hasCascade = !empty($routing) || !empty($peers) || !empty($peerAccess);
        if (!$hasCascade):
        ?>
            <div class="pm-schematic pm-schematic-empty">
                <div class="pm-node pm-node-ghost">No cascade peers / routing in DB</div>
                <span class="pm-arrow-h" aria-hidden="true"></span>
                <div class="pm-node pm-node-direct">
                    <span class="pm-node-title">DIRECT</span>
                    <span class="pm-node-sub">default path if no never_direct</span>
                </div>
            </div>
        <?php else: ?>
            <div class="pm-schematic pm-schematic-cascade">
                <?php if (!empty($routing)): ?>
                    <div class="pm-cascade-block">
                        <div class="pm-cascade-label">Routing</div>
                        <?php foreach ($routing as $rule):
                            $dir = strtolower($rule['directive']);
                            $isDirect = $dir === 'always_direct';
                            $aclAttr = htmlspecialchars(implode(' ', $rule['acls']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        ?>
                        <div class="pm-step pm-step-route" data-acls="<?= $aclAttr ?>">
                            <div class="pm-step-ord">#<?= (int)$rule['order'] ?></div>
                            <div class="pm-step-row">
                                <div class="pm-step-acls">
                                    <?php
                                    if (empty($rule['acls'])) {
                                        echo '<span class="pm-node pm-node-ghost">(no ACL)</span>';
                                    } else {
                                        foreach ($rule['acls'] as $j => $an) {
                                            if ($j > 0) {
                                                echo '<span class="pm-join" aria-hidden="true">+</span>';
                                            }
                                            $renderAclNode($an);
                                        }
                                    }
                                    ?>
                                </div>
                                <div class="pm-edge">
                                    <span class="pm-edge-label"><?= htmlspecialchars($rule['directive'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                    <span class="pm-arrow-h" aria-hidden="true"></span>
                                </div>
                                <?php if ($isDirect): ?>
                                    <div class="pm-node pm-node-direct">
                                        <span class="pm-node-title">DIRECT</span>
                                    </div>
                                <?php else: ?>
                                    <div class="pm-node pm-node-peerish">
                                        <span class="pm-node-title">parent peers</span>
                                        <span class="pm-node-sub">never_direct</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($peers)): ?>
                    <div class="pm-cascade-block">
                        <div class="pm-cascade-label">Peers &amp; cache_peer_access</div>
                        <?php foreach ($peers as $peer):
                            $pid = (int)$peer['id'];
                            $rulesFor = array_values(array_filter($peerAccess, static function ($r) use ($pid) {
                                return (int)$r['peer_id'] === $pid;
                            }));
                            $peerDisabled = ($peer['status'] ?? '') === 'disabled';
                        ?>
                        <div class="pm-peer-card<?= $peerDisabled ? ' pm-step-disabled' : '' ?>">
                            <div class="pm-node pm-node-peer">
                                <span class="pm-node-title"><?= htmlspecialchars($peer['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                <span class="pm-node-sub"><?= htmlspecialchars($peer['hostname'] . ':' . $peer['http_port'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                <?php if (!empty($peer['forward_client_ip'])): ?>
                                    <span class="pm-node-tag">XFF</span>
                                <?php endif; ?>
                            </div>
                            <?php if (empty($rulesFor)): ?>
                                <p class="pm-map-empty">No peer_access → peer unused unless other rules match</p>
                            <?php else: ?>
                                <?php foreach ($rulesFor as $pr):
                                    $action = $pr['action'] === 'allow' ? 'allow' : 'deny';
                                    $aclAttr = htmlspecialchars(implode(' ', $pr['acls']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                ?>
                                <div class="pm-step pm-step-<?= $action ?>" data-acls="<?= $aclAttr ?>">
                                    <div class="pm-step-row">
                                        <div class="pm-step-acls">
                                            <?php
                                            foreach ($pr['acls'] as $j => $an) {
                                                if ($j > 0) {
                                                    echo '<span class="pm-join" aria-hidden="true">+</span>';
                                                }
                                                $renderAclNode($an);
                                            }
                                            ?>
                                        </div>
                                        <div class="pm-edge">
                                            <span class="pm-edge-label">peer_access <?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                            <span class="pm-arrow-h" aria-hidden="true"></span>
                                        </div>
                                        <div class="pm-node pm-node-action pm-node-<?= $action ?>">
                                            <span class="pm-node-title"><?= htmlspecialchars(strtoupper($action), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                            <span class="pm-node-sub"><?= htmlspecialchars($peer['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
(function () {
    var root = document.querySelector('.content-area');
    if (!root) return;
    var selected = null;

    function clear() {
        root.querySelectorAll('.pm-node-acl.is-active, .pm-step.is-related, .pm-peer-card.is-related').forEach(function (el) {
            el.classList.remove('is-active', 'is-related');
        });
    }

    function selectAcl(name) {
        clear();
        if (!name) {
            selected = null;
            return;
        }
        if (selected === name) {
            selected = null;
            return;
        }
        selected = name;
        root.querySelectorAll('.pm-node-acl[data-acl]').forEach(function (btn) {
            if (btn.getAttribute('data-acl') === name) {
                btn.classList.add('is-active');
            }
        });
        root.querySelectorAll('[data-acls]').forEach(function (row) {
            var list = (row.getAttribute('data-acls') || '').split(/\s+/);
            if (list.indexOf(name) !== -1) {
                row.classList.add('is-related');
                var card = row.closest('.pm-peer-card');
                if (card) card.classList.add('is-related');
            }
        });
    }

    root.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.pm-node-acl');
        if (!btn) return;
        ev.preventDefault();
        selectAcl(btn.getAttribute('data-acl') || '');
    });
})();
</script>
