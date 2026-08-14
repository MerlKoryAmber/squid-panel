<div class="page-header">
    <h2>HTTP Access Rules</h2>
    <a href="/http_access/create" class="btn btn-primary">+ Add Rule</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>Rules</h3>
        <span class="subtitle">Drag rows to reorder</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($rules)): ?>
        <div class="empty-state">
            <h4>No rules configured</h4>
            <p>Add your first HTTP access rule to start filtering traffic.</p>
            <a href="/http_access/create" class="btn btn-primary" style="margin-top: var(--space-md);">Add Rule</a>
        </div>
        <?php else: ?>
        <table class="data-table" id="rulesTable">
            <thead>
                <tr>
                    <th style="width:40px;"></th>
                    <th>Order</th>
                    <th>Action</th>
                    <th>ACLs</th>
                    <th>Description</th>
                    <th style="width:140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rules as $rule): ?>
                <tr data-id="<?= $rule['id'] ?>">
                    <td class="drag-handle">⋮⋮</td>
                    <td><?= $rule['sort_order'] ?></td>
                    <td>
                        <span class="badge badge-<?= $rule['action'] === 'allow' ? 'success' : 'danger' ?>">
                            <?= $rule['action'] ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $acls = json_decode($rule['acls'], true) ?? [];
                        foreach ($acls as $acl): ?>
                        <span class="badge badge-default" style="margin-right:4px;"><?= htmlspecialchars($acl) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td style="color: var(--ir-text-secondary);"><?= htmlspecialchars($rule['description'] ?? '') ?></td>
                    <td>
                        <a href="/http_access/edit?id=<?= $rule['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <form method="POST" action="/http_access/delete" style="display:inline" onsubmit="return confirm('Delete this rule?')">
                            <?= View::csrf() ?>
                            <input type="hidden" name="id" value="<?= $rule['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Preview</h3></div>
    <div class="card-body">
        <div class="code-block"><?php foreach ($rules as $rule): ?>http_access <?= $rule['action'] ?> <?= implode(' ', json_decode($rule['acls'], true) ?? []) ?>
<?php endforeach; ?></div>
    </div>
</div>

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
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?? '' ?>' },
                body: JSON.stringify({ order: order })
            }).then(() => location.reload());
        }
    });
}
</script>
