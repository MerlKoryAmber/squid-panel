<div class="page-header">
    <h2>Users</h2>
    <?php if (Auth::isAdmin()): ?>
    <button class="btn btn-primary" onclick="document.getElementById('createUserForm').style.display='block'">+ Add User</button>
    <?php endif; ?>
</div>

<div id="createUserForm" class="panel" style="display:none; margin-bottom: 16px;">
    <div class="panel-header"><h3>New User</h3></div>
    <div class="panel-body">
        <form method="POST" action="/users/store">
            <?= View::csrf() ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role">
                    <option value="operator">Operator</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" class="btn" onclick="document.getElementById('createUserForm').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="panel">
    <table class="data-table">
        <thead>
            <tr><th>Username</th><th>Role</th><th>Created</th><?php if (Auth::isAdmin()): ?><th>Actions</th><?php endif; ?></tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?= htmlspecialchars($user['username']) ?></td>
                <td><span class="badge badge-<?= $user['role'] === 'admin' ? 'success' : '' ?>"><?= htmlspecialchars($user['role']) ?></span></td>
                <td><?= htmlspecialchars($user['created_at'] ?? '') ?></td>
                <?php if (Auth::isAdmin()): ?>
                <td>
                    <form method="POST" action="/users/delete" style="display:inline" onsubmit="return confirm('Delete user <?= htmlspecialchars($user['username']) ?>?')">
                        <?= View::csrf() ?>
                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                        <button type="submit" class="btn-sm btn-danger">Delete</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
