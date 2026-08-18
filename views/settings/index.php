<div class="page-header">
    <h2>Settings</h2>
</div>

<div class="card">
    <div class="card-header"><h3>General</h3></div>
    <div class="card-body">
        <form method="POST" action="/settings/save">
            <?= View::csrf() ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Language</label>
                    <select name="language">
                        <option value="ru" <?= ($settings['language'] ?? 'ru') === 'ru' ? 'selected' : '' ?>>Русский</option>
                        <option value="en" <?= ($settings['language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Theme</label>
                    <select name="theme">
                        <option value="light" <?= ($settings['theme'] ?? 'light') === 'light' ? 'selected' : '' ?>>Light</option>
                        <option value="dark" <?= ($settings['theme'] ?? '') === 'dark' ? 'selected' : '' ?>>Dark</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Change password</h3></div>
    <div class="card-body">
        <?php if (!empty($_SESSION['flash_error'])): ?>
        <p><?= htmlspecialchars($_SESSION['flash_error']) ?></p>
        <?php unset($_SESSION['flash_error']); endif; ?>
        <?php if (!empty($_SESSION['flash_success'])): ?>
        <p><?= htmlspecialchars($_SESSION['flash_success']) ?></p>
        <?php unset($_SESSION['flash_success']); endif; ?>
        <form method="POST" action="/users/password">
            <?= View::csrf() ?>
            <input type="hidden" name="id" value="<?= (int)(Auth::user()['id'] ?? 0) ?>">
            <input type="hidden" name="redirect" value="/settings">
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
