<div class="page-header">
    <h2>NTLM Authentication</h2>
</div>

<div class="card">
    <div class="card-header"><h3>NTLM Settings</h3></div>
    <div class="card-body">
        <form method="POST" action="/auth/ntlm/update">
            <?= View::csrf() ?>
            <div class="form-group">
                <label>Domain Controller</label>
                <input type="text" name="dc" value="<?= htmlspecialchars($config['dc'] ?? '') ?>" placeholder="dc01.domain.local">
            </div>
            <div class="form-group">
                <label>Domain</label>
                <input type="text" name="domain" value="<?= htmlspecialchars($config['domain'] ?? '') ?>" placeholder="DOMAIN">
            </div>
            <div class="form-group">
                <label>Helper Program</label>
                <select name="helper">
                    <option value="ntlm_auth" <?= ($config['helper'] ?? '') === 'ntlm_auth' ? 'selected' : '' ?>>ntlm_auth (Samba)</option>
                    <option value="winbind" <?= ($config['helper'] ?? '') === 'winbind' ? 'selected' : '' ?>>winbind</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Settings</button>
                <a href="/auth/ntlm/test" class="btn btn-secondary">Test Connection</a>
            </div>
        </form>
    </div>
</div>
