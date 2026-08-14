<div class="page-header">
    <h2>Users</h2>
    <?php if (Auth::isAdmin()): ?><a href="/users/create" class="btn btn-primary">+ Add User</a><?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <h3>System Users</h3>
        <span class="subtitle"><?= count($users) ?> total</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Created</th>
                    <th style="width:140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                    <td><span class="badge badge-<?= $user['role'] === 'admin' ? 'danger' : 'default' ?>"><?= $user['role'] ?></span></td>
                    <td style="color:var(--ir-text-muted); font-size:0.82rem;"><?= $user['created_at'] ?></td>
                    <td>
                        <?php if (Auth::isAdmin() && $user['id'] != ($_SESSION['user_id'] ?? 0)): ?>
                        <form method="POST" action="/users/delete" style="display:inline" onsubmit="return confirm('Delete user <?= htmlspecialchars($user['username']) ?>?')">
                            <?= View::csrf() ?>
                            <input type="hidden" name="id" value="<?= $user['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                        <?php else: ?>
                        <span class="text-muted" style="font-size:0.78rem;">Current user</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
