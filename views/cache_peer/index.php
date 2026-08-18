<?php
$isAdmin = !empty($isAdmin);
$creating = !empty($creating);
$selectedId = (int)($peer['id'] ?? 0);
$csrf = View::csrfToken();
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
    Peers are upstream proxies. Access rules decide which requests may use the selected peer.
    “When to use cascade” decides whether Squid must go via a peer or direct.
    Large destination lists belong in an ACL stored as a file (ACLs → Large list), then select that one ACL here — do not paste thousands of sites into Cascade.
</p>

<div class="cascade">
    <aside class="cascade-list card">
        <div class="card-header">
            <h3>Peers</h3>
            <span class="subtitle"><?= count($peers) ?></span>
        </div>
        <div class="cascade-list-body">
            <?php if (empty($peers) && !$creating): ?>
            <div class="empty-state" style="padding: var(--space-lg);">
                <h4>No peers</h4>
                <p>Add a parent proxy to send traffic upstream.</p>
            </div>
            <?php endif; ?>
            <?php if ($creating): ?>
            <div class="cascade-peer-item active">
                <strong>New peer</strong>
                <span class="cascade-peer-meta">Fill connection on the right</span>
            </div>
            <?php endif; ?>
            <?php foreach ($peers as $p): ?>
            <a class="cascade-peer-item<?= (!$creating && (int)$p['id'] === $selectedId) ? ' active' : '' ?>" href="/peers?id=<?= (int)$p['id'] ?>">
                <span class="cascade-peer-top">
                    <strong><?= htmlspecialchars($p['name'] ?: $p['hostname']) ?></strong>
                    <span class="badge badge-<?= ($p['status'] ?? 'active') === 'active' ? 'success' : 'default' ?>"><?= htmlspecialchars($p['status'] ?? 'active') ?></span>
                </span>
                <span class="cascade-peer-meta">
                    <?= htmlspecialchars($p['hostname']) ?>:<?= (int)($p['http_port'] ?? 3128) ?>
                    · <?= htmlspecialchars($p['peer_type']) ?>
                    · <?= (int)($p['rule_count'] ?? 0) ?> rule(s)
                </span>
            </a>
            <?php endforeach; ?>
        </div>
    </aside>

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
                        <button type="submit" form="deletePeerForm" class="btn btn-danger" onclick="return confirm('Delete this peer?')">Delete</button>
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
                                <form method="POST" action="/peers/access/delete" onsubmit="return confirm('Delete this rule?')">
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
            <?php if ($isAdmin): ?>
            <div class="card-body" style="border-top:1px solid var(--ir-border-light);">
                <form method="POST" action="/peers/access/store">
                    <?= View::csrf() ?>
                    <input type="hidden" name="peer_id" value="<?= (int)$peer['id'] ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Action</label>
                            <select name="action">
                                <option value="allow">allow — send matching requests to this peer</option>
                                <option value="deny">deny — do not use this peer</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>ACLs (AND)</label>
                        <?php if (empty($acls)): ?>
                        <p class="text-muted">No ACLs yet. Create them under ACLs first.</p>
                        <?php else: ?>
                        <div class="acl-chip-list">
                            <?php foreach ($acls as $acl): ?>
                            <label class="acl-chip" data-acl-tip="<?= htmlspecialchars(View::aclTipText($acl['name']), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="checkbox" name="acls[]" value="<?= htmlspecialchars($acl['name']) ?>">
                                <?php
                                $meta = View::aclCatalog()[$acl['name']] ?? null;
                                if ($meta) {
                                    echo '<a class="acl-ref" href="/acl/edit?id=' . (int)$meta['id'] . '" data-acl-tip="' . htmlspecialchars(View::aclTipText($acl['name']), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($acl['name']) . '</a>';
                                } else {
                                    echo '<span>' . htmlspecialchars($acl['name']) . '</span>';
                                }
                                ?>
                                <small><?= htmlspecialchars($acl['type']) ?></small>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group" id="lockPathWrap">
                        <label class="acl-chip">
                            <input type="checkbox" name="lock_path" value="1" checked>
                            <span>Forbid DIRECT and other peers</span>
                        </label>
                        <p class="text-muted" style="font-size:0.82rem; margin-top:6px;">
                            Adds <code>never_direct allow</code> with the same ACLs and <code>deny</code> on every other peer (skipped if already present). Uncheck if this peer may be used but DIRECT is still allowed.
                        </p>
                    </div>
                    <div class="form-actions" style="border:0; margin-top:0; padding-top:0;">
                        <button type="submit" class="btn btn-primary" <?= empty($acls) ? 'disabled' : '' ?>>Add rule</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="card">
            <div class="empty-state">
                <h4>Select a peer</h4>
                <p>Choose a peer on the left or add a new upstream.</p>
            </div>
        </div>
        <?php endif; ?>
    </section>
</div>

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
                        <form method="POST" action="/peers/routing/delete" onsubmit="return confirm('Delete this rule?')">
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
    <?php if ($isAdmin): ?>
    <div class="card-body" style="border-top:1px solid var(--ir-border-light);">
        <form method="POST" action="/peers/routing/store">
            <?= View::csrf() ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Policy</label>
                    <select name="intent">
                        <option value="cascade">Use cascade (never_direct)</option>
                        <option value="direct">Direct only (always_direct)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Match</label>
                    <select name="action">
                        <option value="allow">allow</option>
                        <option value="deny">deny</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>ACLs (AND)</label>
                <?php if (empty($acls)): ?>
                <p class="text-muted">No ACLs yet. Create them under ACLs first.</p>
                <?php else: ?>
                <div class="acl-chip-list">
                    <?php foreach ($acls as $acl): ?>
                    <label class="acl-chip" data-acl-tip="<?= htmlspecialchars(View::aclTipText($acl['name']), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="checkbox" name="acls[]" value="<?= htmlspecialchars($acl['name']) ?>">
                        <?php
                        $meta = View::aclCatalog()[$acl['name']] ?? null;
                        if ($meta) {
                            echo '<a class="acl-ref" href="/acl/edit?id=' . (int)$meta['id'] . '" data-acl-tip="' . htmlspecialchars(View::aclTipText($acl['name']), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($acl['name']) . '</a>';
                        } else {
                            echo '<span>' . htmlspecialchars($acl['name']) . '</span>';
                        }
                        ?>
                        <small><?= htmlspecialchars($acl['type']) ?></small>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="form-actions" style="border:0; margin-top:0; padding-top:0;">
                <button type="submit" class="btn btn-primary" <?= empty($acls) ? 'disabled' : '' ?>>Add policy</button>
            </div>
        </form>
    </div>
    <?php endif; ?>
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
(function () {
    const form = document.querySelector('form[action="/peers/access/store"]');
    if (!form) return;
    const action = form.querySelector('select[name="action"]');
    const wrap = document.getElementById('lockPathWrap');
    if (!action || !wrap) return;
    const sync = function () {
        wrap.style.display = action.value === 'allow' ? '' : 'none';
    };
    action.addEventListener('change', sync);
    sync();
})();
</script>
<?php endif; ?>
