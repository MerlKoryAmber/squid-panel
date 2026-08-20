<div class="page-header">
    <h2>Users</h2>
</div>

<?php if (!empty($flashError)): ?>
<div class="card" style="border-color: var(--ir-danger, #c0392b);">
    <div class="card-body"><?= htmlspecialchars($flashError) ?></div>
</div>
<?php endif; ?>
<?php if (!empty($flashSuccess)): ?>
<div class="card" style="border-color: var(--ir-success, #1e7a46);">
    <div class="card-body"><?= htmlspecialchars($flashSuccess) ?></div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Change password</h3>
        <span class="subtitle"><?= htmlspecialchars($currentUser['username'] ?? '') ?></span>
    </div>
    <div class="card-body">
        <form method="POST" action="/users/password">
            <?= View::csrf() ?>
            <input type="hidden" name="id" value="<?= (int)($currentUser['id'] ?? 0) ?>">
            <div class="form-group">
                <label>Current password</label>
                <input type="password" name="current_password" required autocomplete="current-password">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>New password</label>
                    <input type="password" name="password" required minlength="8" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label>Confirm new password</label>
                    <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update password</button>
            </div>
        </form>
    </div>
</div>

<?php if (Auth::isAdmin()): ?>
<div class="card">
    <div class="card-header"><h3>Add user</h3></div>
    <div class="card-body">
        <form method="POST" action="/users/store">
            <?= View::csrf() ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required pattern="[a-zA-Z0-9_-]+">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required minlength="8">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role">
                        <option value="operator">operator</option>
                        <option value="admin">admin</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create user</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

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
                    <th style="width:280px; white-space:nowrap;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                    <td><span class="badge badge-<?= $user['role'] === 'admin' ? 'danger' : 'default' ?>"><?= $user['role'] ?></span></td>
                    <td style="color:var(--ir-text-muted); font-size:0.82rem;"><?= htmlspecialchars($user['created_at'] ?? '') ?></td>
                    <td>
                        <?php if (Auth::isAdmin() && (int)$user['id'] !== (int)($currentUser['id'] ?? 0)): ?>
                        <form method="POST" action="/users/password" style="display:inline-block; margin-right:6px;">
                            <?= View::csrf() ?>
                            <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                            <input type="password" name="password" placeholder="New password" required minlength="8" style="width:120px; display:inline-block;">
                            <input type="hidden" name="password_confirm" value="">
                            <button type="submit" class="btn btn-sm btn-secondary" onclick="this.form.password_confirm.value=this.form.password.value;">Set password</button>
                        </form>
                        <form method="POST" action="/users/delete" style="display:inline" data-confirm="Delete user <?= htmlspecialchars($user['username'], ENT_QUOTES) ?>?">
                            <?= View::csrf() ?>
                            <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
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
