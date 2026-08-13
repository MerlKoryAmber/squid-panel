<div class="page-header"><h2>Settings</h2></div>

<div class="panel">
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
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
</div>
