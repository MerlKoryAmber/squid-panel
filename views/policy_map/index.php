<?php
/** @var list<array> $httpAccess */
/** @var list<array> $peers */
/** @var list<array> $peerAccess */
/** @var list<array> $routing */
/** @var array<string, array> $aclIndex */

$aclType = static function ($name) use ($aclIndex) {
    return (string)($aclIndex[$name]['type'] ?? '');
};
?>
<div class="page-header">
    <h2>Policy map</h2>
    <p class="pm-map-lead">Read-only view from the panel database (what Apply writes to Squid). Squid matches <strong>top → bottom</strong>. Click an ACL to highlight it on both layers.</p>
</div>

<div class="pm-map-legend" aria-hidden="true">
    <span class="pm-map-leg pm-map-leg-allow">allow</span>
    <span class="pm-map-leg pm-map-leg-deny">deny</span>
    <span class="pm-map-leg pm-map-leg-disabled">disabled</span>
    <span class="pm-map-leg pm-map-leg-peer">peer</span>
    <span class="pm-map-leg pm-map-leg-direct">DIRECT</span>
</div>

<section class="card pm-map-layer" id="pm-layer-access">
    <div class="card-header"><h3>HTTP Access <span class="pm-map-hint">order top → bottom</span></h3></div>
    <div class="card-body">
        <?php if (empty($httpAccess)): ?>
            <p class="pm-map-empty">No HTTP Access rules in the database.</p>
        <?php else: ?>
            <ol class="pm-map-flow">
                <?php foreach ($httpAccess as $rule):
                    $action = $rule['action'] === 'allow' ? 'allow' : 'deny';
                    $cls = 'pm-map-rule pm-map-rule-' . $action;
                    if (!$rule['enabled']) {
                        $cls .= ' pm-map-rule-disabled';
                    }
                    $aclAttr = htmlspecialchars(implode(' ', $rule['acls']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                ?>
                <li class="<?= $cls ?>" data-acls="<?= $aclAttr ?>">
                    <span class="pm-map-ord">#<?= (int)$rule['order'] ?></span>
                    <div class="pm-map-rule-body">
                        <div class="pm-map-acls">
                            <?php foreach ($rule['acls'] as $an):
                                $t = $aclType($an);
                            ?>
                                <button type="button" class="pm-map-acl" data-acl="<?= htmlspecialchars($an, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="<?= htmlspecialchars($t !== '' ? $t : 'ACL', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                    <?= htmlspecialchars($an, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                    <?php if ($t !== ''): ?><small><?= htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small><?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                            <?php if (empty($rule['acls'])): ?>
                                <span class="pm-map-muted">(no ACL)</span>
                            <?php endif; ?>
                        </div>
                        <span class="pm-map-action pm-map-action-<?= $action ?>"><?= htmlspecialchars(strtoupper($action), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                        <?php if (!$rule['enabled']): ?>
                            <span class="pm-map-badge">off</span>
                        <?php endif; ?>
                        <?php if ($rule['description'] !== ''): ?>
                            <span class="pm-map-desc"><?= htmlspecialchars($rule['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</section>

<section class="card pm-map-layer" id="pm-layer-cascade">
    <div class="card-header"><h3>Cascade <span class="pm-map-hint">routing + peer access</span></h3></div>
    <div class="card-body pm-map-cascade">
        <div class="pm-map-col">
            <h4>Routing</h4>
            <?php if (empty($routing)): ?>
                <p class="pm-map-empty">No never_direct / always_direct rules.</p>
            <?php else: ?>
                <ol class="pm-map-flow">
                    <?php foreach ($routing as $rule):
                        $dir = strtolower($rule['directive']);
                        $isDirect = $dir === 'always_direct';
                        $cls = 'pm-map-rule ' . ($isDirect ? 'pm-map-rule-direct' : 'pm-map-rule-peer');
                        $aclAttr = htmlspecialchars(implode(' ', $rule['acls']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    ?>
                    <li class="<?= $cls ?>" data-acls="<?= $aclAttr ?>">
                        <span class="pm-map-ord">#<?= (int)$rule['order'] ?></span>
                        <div class="pm-map-rule-body">
                            <div class="pm-map-acls">
                                <?php foreach ($rule['acls'] as $an):
                                    $t = $aclType($an);
                                ?>
                                    <button type="button" class="pm-map-acl" data-acl="<?= htmlspecialchars($an, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                        <?= htmlspecialchars($an, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                        <?php if ($t !== ''): ?><small><?= htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small><?php endif; ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <span class="pm-map-action"><?= htmlspecialchars($rule['directive'] . ' ' . $rule['action'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                            <span class="pm-map-badge"><?= $isDirect ? 'DIRECT' : 'via parent' ?></span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>

        <div class="pm-map-col">
            <h4>Peers &amp; cache_peer_access</h4>
            <?php if (empty($peers)): ?>
                <p class="pm-map-empty">No cache peers.</p>
            <?php else: ?>
                <ul class="pm-map-peers">
                    <?php foreach ($peers as $peer):
                        $pid = (int)$peer['id'];
                        $rulesFor = array_values(array_filter($peerAccess, static function ($r) use ($pid) {
                            return (int)$r['peer_id'] === $pid;
                        }));
                    ?>
                    <li class="pm-map-peer<?= ($peer['status'] ?? '') === 'disabled' ? ' pm-map-peer-disabled' : '' ?>">
                        <div class="pm-map-peer-head">
                            <strong><?= htmlspecialchars($peer['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
                            <span class="pm-map-muted"><?= htmlspecialchars($peer['hostname'] . ':' . $peer['http_port'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                            <?php if (!empty($peer['forward_client_ip'])): ?>
                                <span class="pm-map-badge">XFF</span>
                            <?php endif; ?>
                            <?php if (($peer['status'] ?? '') === 'disabled'): ?>
                                <span class="pm-map-badge">disabled</span>
                            <?php endif; ?>
                        </div>
                        <?php if (empty($rulesFor)): ?>
                            <p class="pm-map-empty">No peer_access rules</p>
                        <?php else: ?>
                            <ul class="pm-map-peer-rules">
                                <?php foreach ($rulesFor as $pr):
                                    $action = $pr['action'] === 'allow' ? 'allow' : 'deny';
                                    $aclAttr = htmlspecialchars(implode(' ', $pr['acls']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                ?>
                                <li class="pm-map-rule pm-map-rule-<?= $action ?>" data-acls="<?= $aclAttr ?>">
                                    <div class="pm-map-acls">
                                        <?php foreach ($pr['acls'] as $an):
                                            $t = $aclType($an);
                                        ?>
                                            <button type="button" class="pm-map-acl" data-acl="<?= htmlspecialchars($an, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                                <?= htmlspecialchars($an, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                                <?php if ($t !== ''): ?><small><?= htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small><?php endif; ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <span class="pm-map-action pm-map-action-<?= $action ?>"><?= htmlspecialchars(strtoupper($action), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
(function () {
    var root = document.querySelector('.content-area');
    if (!root) return;
    var selected = null;

    function clear() {
        root.querySelectorAll('.pm-map-acl.is-active, .pm-map-rule.is-related').forEach(function (el) {
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
        root.querySelectorAll('.pm-map-acl[data-acl]').forEach(function (btn) {
            if (btn.getAttribute('data-acl') === name) {
                btn.classList.add('is-active');
            }
        });
        root.querySelectorAll('.pm-map-rule[data-acls]').forEach(function (row) {
            var list = (row.getAttribute('data-acls') || '').split(/\s+/);
            if (list.indexOf(name) !== -1) {
                row.classList.add('is-related');
            }
        });
    }

    root.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.pm-map-acl');
        if (!btn) return;
        ev.preventDefault();
        selectAcl(btn.getAttribute('data-acl') || '');
    });
})();
</script>
