<?php
$isAdmin = !empty($isAdmin);
$csrf = View::csrfToken();
$ruleRows = is_array($ruleRows ?? null) ? $ruleRows : [];
$peers = is_array($peers ?? null) ? $peers : [];
$fromLists = is_array($fromLists ?? null) ? $fromLists : [];
$cascadeAclLists = is_array($cascadeAclLists ?? null) ? $cascadeAclLists : $fromLists;
$editPeer = $editPeer ?? null;
$newPeer = !empty($newPeer);
?>
<div class="page-header">
    <h2>Cascade</h2>
    <?php if ($isAdmin): ?>
    <a href="/peers?new_peer=1" class="btn btn-secondary">+ Add peer</a>
    <?php endif; ?>
</div>

<p class="text-secondary" style="margin-top:-12px; margin-bottom: var(--space-lg);">
    Route matching clients to an upstream peer or Direct. First match wins. Drag rules to reorder.
    Large destination lists belong in an ACL file (ACLs → Large list).
</p>

<?php if ($isAdmin): ?>
<div class="card" id="cascade-add-rule">
    <div class="card-header">
        <h3>New rule</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="/peers/routes/store">
            <?= View::csrf() ?>
            <div class="cascade-rule-row">
                <div class="form-group">
                    <label>ACL</label>
                    <select name="acls[]" multiple size="10" class="acl-pick" required>
                        <?php foreach ($cascadeAclLists as $item): ?>
                        <option value="<?= htmlspecialchars($item['name']) ?>"><?= htmlspecialchars(PolicyAclKind::label($item, true)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p style="color:var(--ir-text-muted); font-size:0.82rem; margin-top:6px;">Source (src, AD) or destination (dstdomain, dst). Ctrl/Cmd for multiple.</p>
                </div>
                <div class="form-group">
                    <label>Peer</label>
                    <select name="peer_id" required>
                        <option value="">— select —</option>
                        <option value="direct">Direct</option>
                        <?php foreach ($peers as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name'] ?: $p['hostname']) ?> (<?= htmlspecialchars($p['hostname']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-actions" style="border:0; margin-top: var(--space-md); padding-top:0;">
                <button type="submit" class="btn btn-primary" <?= empty($cascadeAclLists) ? 'disabled' : '' ?>>Add rule</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Rules</h3>
        <span class="subtitle">First match wins · drag to reorder</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($ruleRows)): ?>
        <div class="empty-state">
            <h4>No cascade rules yet</h4>
            <p>Add a rule above: source ACLs on the left, peer or Direct on the right.</p>
        </div>
        <?php else: ?>
        <table class="data-table" id="cascadeRulesTable">
            <thead>
                <tr>
                    <?php if ($isAdmin): ?><th style="width:40px;"></th><?php endif; ?>
                    <th>ACL</th>
                    <th>Peer</th>
                    <th>Action</th>
                    <?php if ($isAdmin): ?><th style="width:90px;"></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ruleRows as $rule): ?>
                <tr data-id="<?= (int)$rule['id'] ?>">
                    <?php if ($isAdmin): ?><td class="drag-handle">⋮⋮</td><?php endif; ?>
                    <td>
                        <?php foreach ($rule['acls'] as $acl) {
                            echo View::aclBadge($acl);
                        } ?>
                    </td>
                    <td><strong><?= htmlspecialchars($rule['peer_label']) ?></strong></td>
                    <td>
                        <span class="badge badge-<?= $rule['action'] === 'Direct only' ? 'info' : 'success' ?>">
                            <?= htmlspecialchars($rule['action']) ?>
                        </span>
                    </td>
                    <?php if ($isAdmin): ?>
                    <td>
                        <form method="POST" action="/peers/routes/delete" data-confirm="Delete this rule?">
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

<div class="card">
    <div class="card-header">
        <h3>Upstream peers</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($peers)): ?>
        <div class="empty-state">
            <h4>No peers configured</h4>
            <p>Add an upstream proxy before routing traffic to it.</p>
        </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Hostname</th>
                    <th>Port</th>
                    <th>Status</th>
                    <?php if ($isAdmin): ?><th style="width:140px;"></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($peers as $p): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($p['name'] ?: $p['hostname']) ?></strong></td>
                    <td><?= htmlspecialchars($p['hostname']) ?></td>
                    <td><?= (int)($p['http_port'] ?? 3128) ?></td>
                    <td><span class="badge badge-<?= ($p['status'] ?? 'active') === 'active' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($p['status'] ?? 'active') ?></span></td>
                    <?php if ($isAdmin): ?>
                    <td>
                        <a href="/peers?peer_id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php if ($editPeer): ?>
<div class="card" id="peer-edit">
    <div class="card-header">
        <h3><?= $newPeer ? 'New peer' : 'Edit peer' ?></h3>
        <?php if (!$newPeer): ?>
        <span class="subtitle"><?= htmlspecialchars($editPeer['hostname'] ?? '') ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form method="POST" action="/peers/<?= $newPeer ? 'store' : 'update' ?>">
            <?= View::csrf() ?>
            <?php if (!$newPeer): ?>
            <input type="hidden" name="id" value="<?= (int)$editPeer['id'] ?>">
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($editPeer['name'] ?? '') ?>" placeholder="e.g. ksmg" <?= $isAdmin ? 'required' : 'disabled' ?>>
                </div>
                <div class="form-group">
                    <label>Hostname / IP</label>
                    <input type="text" name="hostname" value="<?= htmlspecialchars($editPeer['hostname'] ?? '') ?>" placeholder="e.g. 172.26.13.230" <?= $isAdmin ? 'required' : 'disabled' ?>>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Type</label>
                    <select name="peer_type" <?= $isAdmin ? '' : 'disabled' ?>>
                        <option value="parent" <?= ($editPeer['peer_type'] ?? 'parent') === 'parent' ? 'selected' : '' ?>>parent</option>
                        <option value="sibling" <?= ($editPeer['peer_type'] ?? '') === 'sibling' ? 'selected' : '' ?>>sibling</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>HTTP port</label>
                    <input type="number" name="http_port" min="1" max="65535" value="<?= (int)($editPeer['http_port'] ?? 3128) ?>" <?= $isAdmin ? 'required' : 'disabled' ?>>
                </div>
                <div class="form-group">
                    <label>ICP port</label>
                    <input type="number" name="icp_port" min="0" max="65535" value="<?= (int)($editPeer['icp_port'] ?? 0) ?>" <?= $isAdmin ? '' : 'disabled' ?>>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" <?= $isAdmin ? '' : 'disabled' ?>>
                        <option value="active" <?= ($editPeer['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>active</option>
                        <option value="disabled" <?= ($editPeer['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>disabled</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Options</label>
                <input type="text" name="options" value="<?= htmlspecialchars($editPeer['options'] ?? '') ?>" placeholder="no-query proxy-only login=PASSTHRU" <?= $isAdmin ? '' : 'disabled' ?>>
            </div>
            <?php if ($isAdmin): ?>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $newPeer ? 'Create peer' : 'Save peer' ?></button>
                <a href="/peers" class="btn btn-secondary">Cancel</a>
                <?php if (!$newPeer): ?>
                <button type="submit" form="deletePeerForm" class="btn btn-danger" data-confirm="Delete this peer?">Delete</button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </form>
        <?php if ($isAdmin && !$newPeer): ?>
        <form id="deletePeerForm" method="POST" action="/peers/delete">
            <?= View::csrf() ?>
            <input type="hidden" name="id" value="<?= (int)$editPeer['id'] ?>">
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($isAdmin && !empty($ruleRows)): ?>
<script src="/assets/js/sortable.js"></script>
<script>
(function () {
    const table = document.getElementById('cascadeRulesTable');
    if (!table) return;
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    new Sortable(tbody, {
        handle: '.drag-handle',
        animation: 150,
        onEnd: function () {
            const order = Array.from(tbody.querySelectorAll('tr')).map(r => r.dataset.id);
            fetch('/peers/routes/reorder', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': <?= json_encode($csrf) ?>
                },
                body: JSON.stringify({
                    order: order,
                    <?= json_encode(CSRF_TOKEN_NAME) ?>: <?= json_encode($csrf) ?>
                })
            });
        }
    });
})();
</script>
<?php endif; ?>
