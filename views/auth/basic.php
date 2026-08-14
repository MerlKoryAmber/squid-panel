<div class="page-header">
    <h2>Basic Authentication</h2>
    <a href="/auth/basic" class="btn btn-secondary">← Back</a>
</div>

<div class="card">
    <div class="card-header"><h3>HTPasswd Users</h3></div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($users)): ?>
        <div class="empty-state"><h4>No users</h4></div>
        <?php else: ?>
        <table class="data-table">
            <thead><tr><th>Username</th><th style="width:120px;">Actions</th></tr></thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($u) ?></strong></td>
                    <td>
                        <form method="POST" action="/auth/basic/delete" style="display:inline">
                            <?= View::csrf() ?>
                            <input type="hidden" name="username" value="<?= htmlspecialchars($u) ?>">
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
    <div class="card-header"><h3>Add User</h3></div>
    <div class="card-body">
        <form method="POST" action="/auth/basic/store">
            <?= View::csrf() ?>
            <div class="form-row">
                <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
                <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Add User</button>
            </div>
        </form>
    </div>
</div>
