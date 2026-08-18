<div class="page-header">
    <h2>Access Control Lists</h2>
    <a href="/acl/create" class="btn btn-primary">+ Add ACL</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>ACLs</h3>
        <span class="subtitle"><?= count($acls) ?> defined</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($acls)): ?>
        <div class="empty-state">
            <h4>No ACLs configured</h4>
            <p>Create ACLs to define traffic groups for filtering.</p>
        </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Values</th>
                    <th style="width:180px; white-space:nowrap;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($acls as $acl): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($acl['name']) ?></strong></td>
                    <td><span class="badge badge-default"><?= htmlspecialchars($acl['type']) ?></span></td>
                    <td style="font-size:0.82rem; color:var(--ir-text-secondary);">
                        <?php
                        $vals = json_decode($acl['entries'] ?? $acl['values'] ?? '[]', true);
                        if (!is_array($vals)) {
                            $vals = [];
                        }
                        echo htmlspecialchars(implode(', ', array_slice($vals, 0, 5)) . (count($vals) > 5 ? '...' : ''));
                        ?>
                    </td>
                    <td>
                        <a href="/acl/edit?id=<?= $acl['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <form method="POST" action="/acl/delete" style="display:inline" onsubmit="return confirm('Delete ACL <?= htmlspecialchars($acl['name']) ?>?')">
                            <?= View::csrf() ?>
                            <input type="hidden" name="id" value="<?= $acl['id'] ?>">
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
