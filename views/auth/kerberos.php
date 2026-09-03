<div class="page-header">
    <h2>Kerberos Authentication</h2>
</div>

<?php if (!empty($flashError)): ?>
<div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>
<?php if (!empty($flashSuccess)): ?>
<div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
<?php endif; ?>

<?php
$keytabPath = $config['keytab_path'] ?? '/etc/squid/proxy.keytab';
$programVal = $config['helper'] ?? '';
if ($programVal === '') {
    $programVal = $config['program'] ?? '/usr/lib64/squid/negotiate_kerberos_auth';
}
$keepAlive = $config['keep_alive'] ?? 'on';
$canTest = !empty($keytabManaged) && !empty($keytabExists) && !empty($isAdmin);
?>

<?php if ($keytabPath !== '' && empty($keytabManaged)): ?>
<div class="alert alert-danger">
    Imported helper uses <code><?= htmlspecialchars($keytabPath) ?></code>.
    kinit/spmd allow only <code>/etc/squid/*.keytab</code>. Upload a copy below; live squid.conf is not rewritten.
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Keytab upload</h3></div>
    <div class="card-body">
        <form method="POST" action="/auth/kerberos/upload" enctype="multipart/form-data">
            <?= View::csrf() ?>
            <div class="form-group">
                <label>MIT keytab file</label>
                <div class="file-pick" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <input type="file" id="keytab-file" name="keytab" accept=".keytab,application/octet-stream" required hidden <?= empty($isAdmin) ? 'disabled' : '' ?>>
                    <?php if (!empty($isAdmin)): ?>
                    <button type="button" class="btn btn-secondary" id="keytab-browse">Choose file</button>
                    <?php endif; ?>
                    <span id="keytab-name" style="color:var(--ir-text-muted); font-size:0.85rem;">No file chosen</span>
                </div>
                <p style="color:var(--ir-text-muted); font-size:0.82rem; margin-top:6px;">Max 512 KB. Installed as <code>/etc/squid/&lt;name&gt;.keytab</code> (mode 640, owner squid).</p>
            </div>
            <div class="form-group">
                <label>Destination filename</label>
                <input type="text" name="dest_name" value="<?= htmlspecialchars($destName ?? 'proxy.keytab') ?>" placeholder="proxy.keytab" <?= empty($isAdmin) ? 'disabled' : '' ?>>
            </div>
            <div class="form-actions">
                <?php if (!empty($isAdmin)): ?>
                <button type="submit" class="btn btn-primary">Upload keytab</button>
                <?php endif; ?>
            </div>
        </form>
        <?php if (!empty($isAdmin)): ?>
        <script>
        (function () {
            var input = document.getElementById('keytab-file');
            var browse = document.getElementById('keytab-browse');
            var name = document.getElementById('keytab-name');
            if (!input || !browse || !name) {
                return;
            }
            browse.addEventListener('click', function () { input.click(); });
            input.addEventListener('change', function () {
                name.textContent = (input.files && input.files[0]) ? input.files[0].name : 'No file chosen';
            });
        })();
        </script>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Kerberos Settings</h3></div>
    <div class="card-body">
        <form method="POST" action="/auth/kerberos/save">
            <?= View::csrf() ?>
            <div class="form-group">
                <label>Helper program</label>
                <input type="text" name="program" value="<?= htmlspecialchars($programVal) ?>" <?= empty($isAdmin) ? 'readonly' : '' ?>>
            </div>
            <div class="form-group">
                <label>Keytab Path</label>
                <input type="text" name="keytab_path" value="<?= htmlspecialchars($keytabPath) ?>" placeholder="/etc/squid/proxy.keytab" <?= empty($isAdmin) ? 'readonly' : '' ?>>
                <p style="color:var(--ir-text-muted); font-size:0.82rem; margin-top:6px;">
                    Panel test/kinit: <code>/etc/squid/*.keytab</code> only.
                    <?php if (!empty($keytabManaged)): ?>
                        File <?= !empty($keytabExists) ? 'present' : 'not found on disk' ?>.
                    <?php endif; ?>
                </p>
            </div>
            <div class="form-group">
                <label>Service Principal</label>
                <input type="text" name="principal" value="<?= htmlspecialchars($config['principal'] ?? '') ?>" placeholder="HTTP/proxy.example.com@REALM" <?= empty($isAdmin) ? 'readonly' : '' ?>>
            </div>
            <div class="form-group">
                <label>KDC Realm</label>
                <input type="text" name="realm" value="<?= htmlspecialchars($config['realm'] ?? '') ?>" <?= empty($isAdmin) ? 'readonly' : '' ?>>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Children</label>
                    <input type="number" name="children" min="1" max="1024" value="<?= (int)($config['children'] ?? 20) ?>" <?= empty($isAdmin) ? 'readonly' : '' ?>>
                    <p style="color:var(--ir-text-muted); font-size:0.82rem; margin-top:6px;">
                        Squid 5/6 <code>auth_param negotiate children</code>: max helper processes (<code>negotiate_kerberos_auth</code>). Too few → Squid waits on a backlog. Sample: 20.
                    </p>
                </div>
                <div class="form-group">
                    <label>Startup</label>
                    <input type="number" name="startup" min="0" max="1024" value="<?= (int)($childrenStartup ?? 0) ?>" <?= empty($isAdmin) ? 'readonly' : '' ?>>
                    <p style="color:var(--ir-text-muted); font-size:0.82rem; margin-top:6px;">
                        <code>startup=N</code>: how many helpers to spawn at start and reconfigure. <code>0</code> = do not pre-start (on demand).
                    </p>
                </div>
                <div class="form-group">
                    <label>Idle</label>
                    <input type="number" name="idle" min="0" max="1024" value="<?= (int)($childrenIdle ?? 10) ?>" <?= empty($isAdmin) ? 'readonly' : '' ?>>
                    <p style="color:var(--ir-text-muted); font-size:0.82rem; margin-top:6px;">
                        <code>idle=N</code>: spare helpers Squid tries to keep ready; it starts more in groups of N, never above Children.
                    </p>
                </div>
            </div>
            <div class="form-group">
                <label>keep_alive</label>
                <select name="keep_alive" <?= empty($isAdmin) ? 'disabled' : '' ?>>
                    <option value="on" <?= $keepAlive === 'on' ? 'selected' : '' ?>>on</option>
                    <option value="off" <?= $keepAlive === 'off' ? 'selected' : '' ?>>off</option>
                </select>
            </div>
            <div class="form-group">
                <label>KDC (panel metadata)</label>
                <input type="text" name="kdc" value="<?= htmlspecialchars($config['kdc'] ?? '') ?>" placeholder="dc01.domain.local" <?= empty($isAdmin) ? 'readonly' : '' ?>>
            </div>
            <div class="form-group">
                <label>Admin Server (panel metadata)</label>
                <input type="text" name="admin_server" value="<?= htmlspecialchars($config['admin_server'] ?? '') ?>" placeholder="dc01.domain.local" <?= empty($isAdmin) ? 'readonly' : '' ?>>
            </div>
            <div class="form-group">
                <label>LDAP servers for AD group helpers</label>
                <textarea name="ldap_servers" rows="3" placeholder="hdc-01.hci.interros.ru&#10;hdc-02.hci.interros.ru" <?= empty($isAdmin) ? 'readonly' : '' ?>><?= htmlspecialchars($config['ldap_servers'] ?? '') ?></textarea>
                <p style="color:var(--ir-text-muted); font-size:0.82rem; margin-top:6px;">
                    Preferred place: <a href="/acl/ad-groups">AD groups → LDAP directory</a> (GSSAPI or simple bind).
                    This field still syncs <code>-S</code> for helpers. FQDN, one per line.
                </p>
            </div>
            <div class="form-actions">
                <?php if (!empty($isAdmin)): ?>
                <button type="submit" class="btn btn-primary">Save Settings</button>
                <?php endif; ?>
            </div>
        </form>
        <?php if (!empty($isAdmin)): ?>
        <form method="POST" action="/auth/kerberos/test" style="margin-top:12px;" onsubmit="var b=this.querySelector('button[type=submit]'); b.disabled=true; b.textContent='Testing…';">
            <?= View::csrf() ?>
            <input type="hidden" name="keytab_path" value="<?= htmlspecialchars($keytabPath) ?>">
            <button type="submit" class="btn btn-secondary" <?= $canTest ? '' : 'disabled' ?>>Test kinit</button>
            <?php if (!$canTest): ?>
            <span style="color:var(--ir-text-muted); font-size:0.82rem; margin-left:8px;">Needs a keytab under /etc/squid</span>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>/etc/krb5.conf (read-only)</h3></div>
    <div class="card-body">
        <p style="color:var(--ir-text-muted); font-size:0.82rem; margin-bottom:8px;">Panel does not rewrite this file.</p>
        <textarea readonly rows="16" style="width:100%; font-family:monospace; font-size:0.8rem;"><?= htmlspecialchars($krb5 ?? '') ?></textarea>
    </div>
</div>
