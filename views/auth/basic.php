<div class="page-header">
    <h2>Basic Authentication</h2>
    <a href="/auth" class="btn btn-secondary">← Back</a>
</div>

<div class="card">
    <div class="card-header"><h3>Helper settings</h3></div>
    <div class="card-body">
        <form method="POST" action="/auth/basic/save">
            <?= View::csrf() ?>
            <div class="form-group">
                <label>Program</label>
                <input type="text" name="program" value="<?= htmlspecialchars($config['program'] ?? '/usr/lib64/squid/basic_ncsa_auth /etc/squid/passwd') ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Children</label>
                    <input type="number" name="children" min="1" max="64" value="<?= (int)($config['children'] ?? 5) ?>">
                </div>
                <div class="form-group">
                    <label>Realm</label>
                    <input type="text" name="realm" value="<?= htmlspecialchars($config['realm'] ?? 'Squid Proxy') ?>">
                </div>
                <div class="form-group">
                    <label>Credentials TTL</label>
                    <input type="text" name="credentialsttl" value="<?= htmlspecialchars($config['credentialsttl'] ?? '2 hours') ?>">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>
</div>
