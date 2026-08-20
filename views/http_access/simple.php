<div class="page-header">
    <h2>Access rules</h2>
    <span class="subtitle">First match wins · drag to reorder</span>
</div>

<?php if (!empty($_SESSION['flash_error'])): ?>
<p class="text-secondary"><?= htmlspecialchars($_SESSION['flash_error']) ?></p>
<?php unset($_SESSION['flash_error']); endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Rules</h3>
        <span class="subtitle">Initiator + traffic filter → action</span>
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
                    <th style="width:200px; white-space:nowrap;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rules as $rule):
                    $p = $rule['_parsed'] ?? ['simple' => false, 'from' => [], 'to' => []];
                    $on = !isset($rule['enabled']) || (int)$rule['enabled'] === 1;
                ?>
                <tr data-id="<?= $rule['id'] ?>" class="<?= $on ? '' : 'is-disabled' ?>">
                    <td class="drag-handle">⋮⋮</td>
                    <td><?= htmlspecialchars(PolicyAclKind::ruleTitle($rule)) ?></td>
                    <td>
                        <?php if (empty($p['simple'])): ?>
                        <span class="badge badge-warning">Complex</span>
                        <?php elseif (empty($p['from'])): ?>
                        <span class="text-secondary">Any initiator</span>
                        <?php else: ?>
                        <?php foreach ($p['from'] as $n) {
                            $meta = $catalog[$n] ?? ['name' => $n];
                            echo '<span class="badge badge-default">' . htmlspecialchars(PolicyAclKind::label($meta)) . '</span> ';
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
                            echo '<span class="badge badge-default">' . htmlspecialchars(PolicyAclKind::label($meta)) . '</span> ';
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
                    <td>
                        <?php if (!empty($p['simple'])): ?>
                        <a href="/http_access/edit?id=<?= $rule['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <?php else: ?>
                        <form method="POST" action="/ui/policy-mode" style="display:inline">
                            <?= View::csrf() ?>
                            <input type="hidden" name="mode" value="expert">
                            <input type="hidden" name="return" value="/http_access/edit?id=<?= (int)$rule['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-secondary">Open in expert</button>
                        </form>
                        <?php endif; ?>
                        <?php if (!empty($isAdmin)): ?>
                        <form method="POST" action="/http_access/toggle" style="display:inline">
                            <?= View::csrf() ?>
                            <input type="hidden" name="id" value="<?= $rule['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-secondary"><?= $on ? 'Disable' : 'Enable' ?></button>
                        </form>
                        <form method="POST" action="/http_access/delete" style="display:inline" data-confirm="Delete this rule?">
                            <?= View::csrf() ?>
                            <input type="hidden" name="id" value="<?= $rule['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($isAdmin)): ?>
<div class="card">
    <div class="card-header"><h3>Add access rule</h3></div>
    <div class="card-body">
        <form method="POST" action="/http_access/store">
            <?= View::csrf() ?>
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" required placeholder="e.g. Accountants → banks">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Initiator</label>
                    <select name="from[]" multiple size="8">
                        <?php foreach ($fromLists as $item): ?>
                        <option value="<?= htmlspecialchars($item['name']) ?>"><?= htmlspecialchars(PolicyAclKind::label($item, true)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-secondary" style="margin-top:6px;font-size:0.8rem;">Who starts the connection (IP, user, group). Empty = any initiator.</p>
                </div>
                <div class="form-group">
                    <label>Traffic filter</label>
                    <select name="to[]" multiple size="8">
                        <?php foreach ($toLists as $item): ?>
                        <option value="<?= htmlspecialchars($item['name']) ?>"><?= htmlspecialchars(PolicyAclKind::label($item, true)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-secondary" style="margin-top:6px;font-size:0.8rem;">Where the request goes (sites / URL lists). Empty = any URL.</p>
                </div>
                <div class="form-group">
                    <label>Action</label>
                    <select name="action" required>
                        <option value="allow">Allow</option>
                        <option value="deny">Deny</option>
                    </select>
                    <label class="acl-chip" style="margin-top:12px;">
                        <input type="checkbox" name="enabled" value="1" checked>
                        <span>Rule is on</span>
                    </label>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" <?= (empty($fromLists) && empty($toLists)) ? 'disabled' : '' ?>>Add</button>
            </div>
        </form>
        <?php if (empty($fromLists) && empty($toLists)): ?>
        <p class="text-secondary">Create initiator or URL lists first (Lists in the menu).</p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script src="/assets/js/sortable.js"></script>
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
