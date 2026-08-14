<div class="page-header">
    <h2>Kerberos Authentication</h2>
</div>

<div class="card">
    <div class="card-header"><h3>Kerberos Settings</h3></div>
    <div class="card-body">
        <form method="POST" action="/auth/kerberos/update">
            <?= View::csrf() ?>
            <div class="form-group">
                <label>Keytab Path</label>
                <input type="text" name="keytab_path" value="<?= htmlspecialchars($config['keytab_path'] ?? '/etc/squid/proxy.keytab') ?>">
            </div>
            <div class="form-group">
                <label>Service Principal</label>
                <input type="text" name="principal" value="<?= htmlspecialchars($config['principal'] ?? 'HTTP/proxy@DOMAIN.LOCAL') ?>">
            </div>
            <div class="form-group">
                <label>KDC Realm</label>
                <input type="text" name="realm" value="<?= htmlspecialchars($config['realm'] ?? 'DOMAIN.LOCAL') ?>">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Settings</button>
                <a href="/auth/kerberos/test" class="btn btn-secondary">Test Connection</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Status</h3></div>
    <div class="card-body">
        <?php if (!empty($testResult)): ?>
        <div class="alert alert-<?= $testResult['success'] ? 'success' : 'danger' ?>">
            <?= htmlspecialchars($testResult['message']) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
