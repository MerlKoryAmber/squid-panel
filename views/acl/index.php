<div class="page-header">
    <h2>ACL Management</h2>
    <?php if (Auth::isAdmin()): ?>
    <a href="/acl/create" class="btn btn-primary">+ New ACL</a>
    <?php endif; ?>
</div>

<div class="panel">
    <table class="data-table">
        <thead>
            <tr><th>Name</th><th>Type</th><th>Values</th><th>Group</th><th>Description</th><?php if (Auth::isAdmin()): ?><th>Actions</th><?php endif; ?></tr>
        </thead>
        <tbody>
            <?php foreach ($acls as $acl): 
                $vals = json_decode($acl['entries'], true) ?: [];
            ?>
            <tr>
                <td><code><?= htmlspecialchars($acl['name']) ?></code></td>
                <td><span class="badge"><?= htmlspecialchars($acl['type']) ?></span></td>
                <td class="truncate"><?= htmlspecialchars(implode(', ', array_slice($vals, 0, 3))) ?><?= count($vals) > 3 ? ' +' . (count($vals)-3) : '' ?></td>
                <td><?= htmlspecialchars($acl['group_name'] ?: '-') ?></td>
                <td><?= htmlspecialchars($acl['description'] ?: '-') ?></td>
                <?php if (Auth::isAdmin()): ?>
                <td>
                    <a href="/acl/edit?id=<?= $acl['id'] ?>" class="btn-sm">Edit</a>
                    <form method="POST" action="/acl/delete" style="display:inline" onsubmit="return confirm('Delete ACL <?= htmlspecialchars($acl['name']) ?>?')">
                        <?= View::csrf() ?>
                        <input type="hidden" name="id" value="<?= $acl['id'] ?>">
                        <button type="submit" class="btn-sm btn-danger">Delete</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
