<?php
$drawerRule = $drawerRule ?? null;
$drawerAdd = !empty($drawerAdd);
$drawerOpen = $drawerAdd || !empty($drawerRule);
$drParsed = $drawerRule['_parsed'] ?? ['simple' => true, 'from' => [], 'to' => []];
$drOn = $drawerRule ? (!isset($drawerRule['enabled']) || (int)$drawerRule['enabled'] === 1) : true;
$drTitle = $drawerRule ? PolicyAclKind::ruleTitle($drawerRule) : '';
if ($drTitle === ('Rule #' . (int)($drawerRule['id'] ?? 0))) {
    $drTitle = '';
}
?>
<div class="page-header">
    <h2>Access rules</h2>
    <span class="subtitle">First match wins · drag to reorder · click a row to edit</span>
</div>

<?php if (!empty($_SESSION['flash_error'])): ?>
<p class="text-secondary"><?= htmlspecialchars($_SESSION['flash_error']) ?></p>
<?php unset($_SESSION['flash_error']); endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Rules</h3>
        <?php if (!empty($isAdmin)): ?>
        <a href="/http_access?add=1" class="btn btn-sm btn-primary" id="spm-rule-add">Add rule</a>
        <?php endif; ?>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($rules)): ?>
        <div class="empty-state">
            <h4>No rules</h4>
            <p>Add who may reach which sites.</p>
        </div>
        <?php else: ?>
        <table class="data-table" id="rulesTable">
            <thead>
                <tr>
                    <th style="width:40px;"></th>
                    <th>Name</th>
                    <th>Initiator</th>
                    <th>Traffic filter</th>
                    <th>Action</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rules as $rule):
                    $p = $rule['_parsed'] ?? ['simple' => false, 'from' => [], 'to' => []];
                    $on = !isset($rule['enabled']) || (int)$rule['enabled'] === 1;
                    $title = PolicyAclKind::ruleTitle($rule);
                    $payload = [
                        'id' => (int)$rule['id'],
                        'name' => ($title === ('Rule #' . $rule['id'])) ? '' : $title,
                        'action' => $rule['action'],
                        'enabled' => $on,
                        'from' => $p['from'],
                        'to' => $p['to'],
                        'simple' => !empty($p['simple']),
                    ];
                    $sel = ($drawerRule && (int)$drawerRule['id'] === (int)$rule['id']) ? ' is-selected' : '';
                ?>
                <tr data-id="<?= $rule['id'] ?>"
                    data-rule="<?= htmlspecialchars(json_encode($payload, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>"
                    class="<?= $on ? '' : 'is-disabled' ?><?= $sel ?>">
                    <td class="drag-handle">⋮⋮</td>
                    <td><a href="/http_access?edit=<?= (int)$rule['id'] ?>"><?= htmlspecialchars($title) ?></a></td>
                    <td>
                        <?php if (empty($p['simple'])): ?>
                        <span class="badge badge-warning">Complex</span>
                        <form method="POST" action="/ui/policy-mode" style="display:inline">
                            <?= View::csrf() ?>
                            <input type="hidden" name="mode" value="expert">
                            <input type="hidden" name="return" value="/http_access/edit?id=<?= (int)$rule['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-secondary">Open in expert</button>
                        </form>
                        <?php elseif (empty($p['from'])): ?>
                        <span class="text-secondary">Any initiator</span>
                        <?php else: ?>
                        <?php foreach ($p['from'] as $n) {
                            $meta = $catalog[$n] ?? ['name' => $n];
                            echo '<span class="badge badge-default spm-preview-badge" title="' . htmlspecialchars(PolicyAclKind::labelWithPreview($meta)) . '">' . htmlspecialchars(PolicyAclKind::labelWithPreview($meta)) . '</span> ';
                        } ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (empty($p['simple'])): ?>
                        —
                        <?php elseif (empty($p['to'])): ?>
                        <span class="text-secondary">Any URL</span>
                        <?php else: ?>
                        <?php foreach ($p['to'] as $n) {
                            $meta = $catalog[$n] ?? ['name' => $n];
                            echo '<span class="badge badge-default spm-preview-badge" title="' . htmlspecialchars(PolicyAclKind::labelWithPreview($meta)) . '">' . htmlspecialchars(PolicyAclKind::labelWithPreview($meta)) . '</span> ';
                        } ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= $rule['action'] === 'allow' ? 'success' : 'danger' ?>">
                            <?= $rule['action'] === 'allow' ? 'Allow' : 'Deny' ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-<?= $on ? 'success' : 'default' ?>"><?= $on ? 'On' : 'Off' ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<div id="spm-rule-drawer" class="spm-drawer" role="dialog" aria-modal="true" aria-labelledby="spm-rule-drawer-title" <?= $drawerOpen ? '' : 'hidden' ?>>
    <div class="spm-drawer-backdrop" data-drawer-close></div>
    <div class="spm-drawer-panel">
        <div class="spm-drawer-head">
            <h3 id="spm-rule-drawer-title"><?= $drawerRule ? 'Edit rule' : 'Add rule' ?></h3>
            <button type="button" class="spm-drawer-close" data-drawer-close aria-label="Close">×</button>
        </div>
        <form method="POST" action="<?= $drawerRule ? '/http_access/update' : '/http_access/store' ?>" id="spm-rule-form" style="display:flex;flex-direction:column;height:100%;min-height:0;">
            <?= View::csrf() ?>
            <input type="hidden" name="id" value="<?= $drawerRule ? (int)$drawerRule['id'] : '' ?>">
            <div class="spm-drawer-body">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" required placeholder="e.g. Accountants → banks" value="<?= htmlspecialchars($drTitle) ?>" <?= empty($isAdmin) ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                    <label>Action</label>
                    <select name="action" required <?= empty($isAdmin) ? 'disabled' : '' ?>>
                        <option value="allow" <?= (!$drawerRule || ($drawerRule['action'] ?? '') === 'allow') ? 'selected' : '' ?>>Allow</option>
                        <option value="deny" <?= ($drawerRule['action'] ?? '') === 'deny' ? 'selected' : '' ?>>Deny</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Initiator</label>
                    <select name="from[]" multiple size="8" <?= empty($isAdmin) ? 'disabled' : '' ?>>
                        <?php foreach ($fromLists as $item): ?>
                        <option value="<?= htmlspecialchars($item['name']) ?>" <?= in_array($item['name'], $drParsed['from'], true) ? 'selected' : '' ?>>
                            <?= htmlspecialchars(PolicyAclKind::labelWithPreview($item)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-hint">Who starts the connection. Empty = any initiator. Members are edited in Lists.</p>
                </div>
                <div class="form-group">
                    <label>Traffic filter</label>
                    <select name="to[]" multiple size="8" <?= empty($isAdmin) ? 'disabled' : '' ?>>
                        <?php foreach ($toLists as $item): ?>
                        <option value="<?= htmlspecialchars($item['name']) ?>" <?= in_array($item['name'], $drParsed['to'], true) ? 'selected' : '' ?>>
                            <?= htmlspecialchars(PolicyAclKind::labelWithPreview($item)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-hint">Where the request goes. Empty = any URL. Members are edited in Lists.</p>
                </div>
                <div class="form-group">
                    <label class="acl-chip">
                        <input type="checkbox" name="enabled" value="1" <?= $drOn ? 'checked' : '' ?> <?= empty($isAdmin) ? 'disabled' : '' ?>>
                        <span>Enabled</span>
                    </label>
                </div>
            </div>
            <div class="spm-drawer-foot">
                <div class="spm-drawer-foot-left" id="spm-rule-drawer-extra" <?= $drawerRule && !empty($isAdmin) ? '' : 'hidden' ?>>
                    <?php if (!empty($isAdmin)): ?>
                    <button type="submit" form="spm-rule-delete" class="btn btn-sm btn-danger">Delete</button>
                    <button type="submit" form="spm-rule-toggle" class="btn btn-sm btn-secondary"><?= $drOn ? 'Disable' : 'Enable' ?></button>
                    <?php endif; ?>
                </div>
                <div class="spm-drawer-foot-right">
                    <button type="button" class="btn btn-secondary" data-drawer-close>Cancel</button>
                    <?php if (!empty($isAdmin)): ?>
                    <button type="submit" class="btn btn-primary" <?= (empty($fromLists) && empty($toLists)) ? 'disabled' : '' ?>>Save</button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>
<?php if (!empty($isAdmin)): ?>
<form method="POST" action="/http_access/toggle" id="spm-rule-toggle" hidden>
    <?= View::csrf() ?>
    <input type="hidden" name="id" value="<?= $drawerRule ? (int)$drawerRule['id'] : '' ?>">
</form>
<form method="POST" action="/http_access/delete" id="spm-rule-delete" hidden data-confirm="Delete this rule?">
    <?= View::csrf() ?>
    <input type="hidden" name="id" value="<?= $drawerRule ? (int)$drawerRule['id'] : '' ?>">
</form>
<?php if (empty($fromLists) && empty($toLists)): ?>
<p class="text-secondary" style="margin-top:12px;">Create initiator or URL lists first (Lists in the menu).</p>
<?php endif; ?>
<?php endif; ?>

<script src="/assets/js/sortable.js"></script>
<script src="/assets/js/rule-drawer.js"></script>
<script>
const table = document.getElementById('rulesTable');
if (table) {
    new Sortable(table.querySelector('tbody'), {
        handle: '.drag-handle',
        animation: 150,
        onEnd: function() {
            const rows = table.querySelectorAll('tbody tr');
            const order = Array.from(rows).map(r => r.dataset.id);
            fetch('/http_access/reorder', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?= htmlspecialchars(View::csrfToken(), ENT_QUOTES) ?>'
                },
                body: JSON.stringify({
                    order: order,
                    <?= json_encode(CSRF_TOKEN_NAME) ?>: <?= json_encode(View::csrfToken()) ?>
                })
            }).then(() => location.reload());
        }
    });
}
</script>
