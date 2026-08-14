<div class="page-header">
    <h2>Settings</h2>
</div>

<div class="card">
    <div class="card-header"><h3>General</h3></div>
    <div class="card-body">
        <form method="POST" action="/settings/update">
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
    <div class="card-header"><h3>Configuration</h3></div>
    <div class="card-body">
        <div style="display:flex; flex-direction:column; gap:10px;">
            <a href="/settings/export" class="btn btn-secondary">📥 Export Configuration</a>
            <a href="/settings/import" class="btn btn-secondary">📤 Import Configuration</a>
            <a href="/settings/reset" class="btn btn-danger" onclick="return confirm('Reset all settings to defaults?')">↺ Reset to Defaults</a>
        </div>
    </div>
</div>
