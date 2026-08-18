<div class="page-header">
    <h2>Kerberos Authentication</h2>
</div>

<div class="card">
    <div class="card-header"><h3>Kerberos Settings</h3></div>
    <div class="card-body">
        <form method="POST" action="/auth/kerberos/save">
            <?= View::csrf() ?>
            <div class="form-group">
                <label>Keytab Path</label>
                <input type="text" name="keytab_path" value="<?= htmlspecialchars($config['keytab_path'] ?? '/etc/squid/proxy.keytab') ?>" placeholder="/etc/squid/proxy.keytab">
                <p style="color:var(--ir-text-muted); font-size:0.82rem; margin-top:6px;">Only an absolute .keytab path under /etc/squid is allowed.</p>
            </div>
            <div class="form-group">
                <label>Service Principal</label>
                <input type="text" name="principal" value="<?= htmlspecialchars($config['principal'] ?? 'HTTP/proxy@DOMAIN.LOCAL') ?>">
            </div>
            <div class="form-group">
                <label>KDC Realm</label>
                <input type="text" name="realm" value="<?= htmlspecialchars($config['realm'] ?? 'DOMAIN.LOCAL') ?>">
            </div>
            <div class="form-group">
                <label>KDC</label>
                <input type="text" name="kdc" value="<?= htmlspecialchars($config['kdc'] ?? '') ?>" placeholder="dc01.domain.local">
            </div>
            <div class="form-group">
                <label>Admin Server</label>
                <input type="text" name="admin_server" value="<?= htmlspecialchars($config['admin_server'] ?? '') ?>" placeholder="dc01.domain.local">
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
