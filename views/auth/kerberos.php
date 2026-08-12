<div class="page-header"><h2>Kerberos (Negotiate) Authentication</h2></div>

<div class="panel">
    <form method="POST" action="/auth/kerberos/save">
        <?= View::csrf() ?>
        <div class="form-row">
            <div class="form-group">
                <label>Realm</label>
                <input type="text" name="realm" value="<?= htmlspecialchars($config['realm'] ?? 'EXAMPLE.COM') ?>" required>
            </div>
            <div class="form-group">
                <label>KDC Server</label>
                <input type="text" name="kdc" value="<?= htmlspecialchars($config['dc'] ?? 'dc.example.com') ?>" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Admin Server</label>
                <input type="text" name="admin_server" value="<?= htmlspecialchars($config['backup_dc'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Keytab Path</label>
                <input type="text" name="keytab_path" value="<?= htmlspecialchars($config['keytab_path'] ?? '/etc/squid/proxy.keytab') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Helper Program</label>
                <input type="text" name="program" value="<?= htmlspecialchars($config['program'] ?? '/usr/lib64/squid/negotiate_kerberos_auth') ?>">
            </div>
            <div class="form-group">
                <label>Children</label>
                <input type="number" name="children" value="<?= htmlspecialchars($config['children'] ?? 10) ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Current /etc/krb5.conf</label>
            <pre class="code-block"><?= htmlspecialchars($krb5 ?: 'File not found or not readable') ?></pre>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary" <?= Auth::isAdmin() ? '' : 'disabled' ?>>Save Configuration</button>
            <?php if (Auth::isAdmin()): ?>
            <button type="button" class="btn" onclick="testKerberos()">Test kinit</button>
            <?php endif; ?>
        </div>
    </form>
    <div id="kerberosTestResult" class="message-box" style="display:none; margin-top: 12px;"></div>
</div>
