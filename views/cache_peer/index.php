<?php
$isAdmin = !empty($isAdmin);
$creating = !empty($creating);
$selectedId = (int)($peer['id'] ?? 0);
$csrf = View::csrfToken();
$directTab = !empty($directTab);
$catalog = $catalog ?? [];
$fromLists = $fromLists ?? [];
$toLists = $toLists ?? [];
$aclTokens = function ($entries) {
    $parts = preg_split('/\s+/', trim((string)$entries));
    return array_values(array_filter($parts, 'strlen'));
};
?>
<div class="page-header">
    <h2>Cascade</h2>
    <?php if ($isAdmin): ?>
    <a href="/peers?new=1" class="btn btn-primary">+ Add peer</a>
    <?php endif; ?>
</div>

<p class="text-secondary" style="margin-top:-12px; margin-bottom: var(--space-lg);">
    Peers are upstream proxies. Switch peers with the tabs. A new rule is From + To for the open tab.
    On a peer tab matching traffic goes <strong>only</strong> to that peer: no DIRECT, no other peers.
    Large destination lists belong in an ACL stored as a file (ACLs → Large list).
</p>

<nav class="peer-tabs" aria-label="Peers">
    <?php foreach ($peers as $p): ?>
    <a class="peer-tab<?= (!$creating && !$directTab && (int)$p['id'] === $selectedId) ? ' active' : '' ?>" href="/peers?id=<?= (int)$p['id'] ?>">
        <?= htmlspecialchars($p['name'] ?: $p['hostname']) ?>
        <small><?= htmlspecialchars($p['status'] ?? 'active') ?></small>
    </a>
    <?php endforeach; ?>
    <a class="peer-tab<?= $directTab ? ' active' : '' ?>" href="/peers?direct=1">Direct</a>
    <?php if ($creating): ?>
    <span class="peer-tab active">New peer</span>
    <?php endif; ?>
</nav>

<div class="cascade">
    <section class="cascade-detail">
        <?php if ($creating || $peer): ?>
        <div class="card">
            <div class="card-header">
                <h3><?= $creating ? 'New peer' : htmlspecialchars($peer['name'] ?: $peer['hostname']) ?></h3>
                <?php if (!$creating): ?>
                <span class="subtitle"><?= htmlspecialchars($peer['hostname']) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST" action="/peers/<?= $creating ? 'store' : 'update' ?>">
                    <?= View::csrf() ?>
                    <?php if (!$creating): ?>
                    <input type="hidden" name="id" value="<?= (int)$peer['id'] ?>">
                    <?php endif; ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($peer['name'] ?? '') ?>" placeholder="e.g. ksmg" <?= $isAdmin ? 'required' : 'disabled' ?>>
                        </div>
                        <div class="form-group">
                            <label>Hostname / IP</label>
                            <input type="text" name="hostname" value="<?= htmlspecialchars($peer['hostname'] ?? '') ?>" placeholder="e.g. 172.26.13.230" <?= $isAdmin ? 'required' : 'disabled' ?>>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Type</label>
                            <select name="peer_type" <?= $isAdmin ? '' : 'disabled' ?>>
                                <option value="parent" <?= ($peer['peer_type'] ?? 'parent') === 'parent' ? 'selected' : '' ?>>parent</option>
                                <option value="sibling" <?= ($peer['peer_type'] ?? '') === 'sibling' ? 'selected' : '' ?>>sibling</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>HTTP port</label>
                            <input type="number" name="http_port" min="1" max="65535" value="<?= (int)($peer['http_port'] ?? 3128) ?>" <?= $isAdmin ? 'required' : 'disabled' ?>>
                        </div>
                        <div class="form-group">
                            <label>ICP port</label>
                            <input type="number" name="icp_port" min="0" max="65535" value="<?= (int)($peer['icp_port'] ?? 0) ?>" <?= $isAdmin ? '' : 'disabled' ?>>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" <?= $isAdmin ? '' : 'disabled' ?>>
                                <option value="active" <?= ($peer['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>active</option>
                                <option value="disabled" <?= ($peer['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>disabled</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Options</label>
                        <input type="text" name="options" value="<?= htmlspecialchars($peer['options'] ?? '') ?>" placeholder="no-query proxy-only login=PASSTHRU" <?= $isAdmin ? '' : 'disabled' ?>>
                    </div>
                    <?php if ($isAdmin): ?>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?= $creating ? 'Create peer' : 'Save peer' ?></button>
                        <?php if ($creating): ?>
                        <a href="/peers" class="btn btn-secondary">Cancel</a>
                        <?php elseif ($peer): ?>
                        <button type="submit" form="deletePeerForm" class="btn btn-danger" data-confirm="Delete this peer?">Delete</button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </form>
                <?php if ($isAdmin && $peer && !$creating): ?>
                <form id="deletePeerForm" method="POST" action="/peers/delete">
                    <?= View::csrf() ?>
                    <input type="hidden" name="id" value="<?= (int)$peer['id'] ?>">
                </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($peer && !$creating): ?>
        <div class="card">
            <div class="card-header">
                <h3>Who may use this peer</h3>
                <span class="subtitle">cache_peer_access · first match wins · drag to reorder</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($accessRules)): ?>
                <div class="empty-state">
                    <h4>No access rules</h4>
                    <p>Without allow rules Squid may send unmatched traffic to this peer. Add allow/deny with ACLs.</p>
                </div>
                <?php else: ?>
                <table class="data-table" id="peerAccessTable">
                    <thead>
                        <tr>
                            <?php if ($isAdmin): ?><th style="width:40px;"></th><?php endif; ?>
                            <th>Action</th>
                            <th>ACLs</th>
                            <?php if ($isAdmin): ?><th style="width:90px;"></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accessRules as $rule): ?>
                        <tr data-id="<?= (int)$rule['id'] ?>">
                            <?php if ($isAdmin): ?><td class="drag-handle">⋮⋮</td><?php endif; ?>
                            <td>
                                <span class="badge badge-<?= $rule['action'] === 'allow' ? 'success' : 'danger' ?>">
                                    <?= $rule['action'] === 'allow' ? 'allow → peer' : 'deny → skip peer' ?>
                                </span>
                            </td>
                            <td>
                                <?php foreach ($aclTokens($rule['acl_entries'] ?? $rule['acl_name'] ?? '') as $acl) {
                                    echo View::aclBadge($acl);
                                } ?>
                            </td>
                            <?php if ($isAdmin): ?>
                            <td>
                                <form method="POST" action="/peers/access/delete" data-confirm="Delete this rule?">
                                    <?= View::csrf() ?>
                                    <input type="hidden" name="id" value="<?= (int)$rule['id'] ?>">
                                    <input type="hidden" name="peer_id" value="<?= (int)$peer['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php elseif ($directTab): ?>
        <div class="card">
            <div class="empty-state" style="padding: var(--space-lg);">
                <h4>Direct</h4>
                <p>New rule on this tab is always_direct: matching traffic does not use cascade.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="empty-state">
                <h4>No peer yet</h4>
                <p>Add an upstream or open the Direct tab.</p>
            </div>
        </div>
        <?php endif; ?>
    </section>
</div>

<?php if ($isAdmin && !$creating && ($peer || $directTab)): ?>
<div class="card" id="cascade-add-route">
    <div class="card-header">
        <h3>New rule</h3>
        <span class="subtitle"><?= $directTab ? 'Direct only · always_direct' : 'From + To · only this peer, never DIRECT' ?></span>
    </div>
    <div class="card-body">
        <form method="POST" action="/peers/routes/store">
            <?= View::csrf() ?>
            <input type="hidden" name="target" value="<?= $directTab ? 'direct' : (int)$selectedId ?>">
            <div class="cascade-rule-row">
                <div class="form-group">
                    <label>From</label>
                    <select name="from[]" multiple size="8">
                        <?php foreach ($fromLists as $item): ?>
                        <option value="<?= htmlspecialchars($item['name']) ?>"><?= htmlspecialchars(PolicyAclKind::label($item, true)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>To</label>
                    <select name="to[]" multiple size="8">
                        <?php foreach ($toLists as $item): ?>
                        <option value="<?= htmlspecialchars($item['name']) ?>"><?= htmlspecialchars(PolicyAclKind::label($item, true)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-actions" style="border:0; margin-top: var(--space-md); padding-top:0;">
                <button type="submit" class="btn btn-primary" <?= (empty($fromLists) && empty($toLists)) || (!$directTab && $selectedId <= 0) ? 'disabled' : '' ?>>Add rule</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card" id="cascade-when">
    <div class="card-header">
        <h3>When to use cascade</h3>
        <span class="subtitle">never_direct / always_direct · first match wins · drag to reorder</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($routingRules)): ?>
        <div class="empty-state">
            <h4>No cascade policy yet</h4>
            <p>“Use cascade” is never_direct allow. “Direct only” is always_direct allow.</p>
        </div>
        <?php else: ?>
        <table class="data-table" id="routingTable">
            <thead>
                <tr>
                    <?php if ($isAdmin): ?><th style="width:40px;"></th><?php endif; ?>
                    <th>Policy</th>
                    <th>Match</th>
                    <th>ACLs</th>
                    <?php if ($isAdmin): ?><th style="width:90px;"></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($routingRules as $rule):
                    $isDirect = ($rule['directive'] ?? '') === 'always_direct';
                    $policy = $isDirect ? 'Direct only' : 'Use cascade';
                ?>
                <tr data-id="<?= (int)$rule['id'] ?>">
                    <?php if ($isAdmin): ?><td class="drag-handle">⋮⋮</td><?php endif; ?>
                    <td><span class="badge badge-<?= $isDirect ? 'info' : 'success' ?>"><?= $policy ?></span></td>
                    <td><?= htmlspecialchars($rule['action']) ?></td>
                    <td>
                        <?php foreach ($aclTokens($rule['acl_name'] ?? '') as $acl) {
                            echo View::aclBadge($acl);
                        } ?>
                    </td>
                    <?php if ($isAdmin): ?>
                    <td>
                        <form method="POST" action="/peers/routing/delete" data-confirm="Delete this rule?">
                            <?= View::csrf() ?>
                            <input type="hidden" name="id" value="<?= (int)$rule['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php if ($isAdmin): ?>
<script src="/assets/js/sortable.js"></script>
<script>
function bindReorder(tableId, url, extra) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    new Sortable(tbody, {
        handle: '.drag-handle',
        animation: 150,
        onEnd: function () {
            const order = Array.from(tbody.querySelectorAll('tr')).map(r => r.dataset.id);
            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': <?= json_encode($csrf) ?>
                },
                body: JSON.stringify(Object.assign({
                    order: order,
                    <?= json_encode(CSRF_TOKEN_NAME) ?>: <?= json_encode($csrf) ?>
                }, extra || {}))
            });
        }
    });
}
bindReorder('peerAccessTable', '/peers/access/reorder', { peer_id: <?= (int)$selectedId ?> });
bindReorder('routingTable', '/peers/routing/reorder', {});
</script>
<?php endif; ?>
