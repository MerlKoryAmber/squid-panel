<div class="page-header"><h2>NTLM (Winbind) Authentication</h2></div>
<div class="panel">
    <form method="POST" action="/auth/ntlm/save">
        <?= View::csrf() ?>
        <div class="form-row">
            <div class="form-group">
                <label>Program</label>
                <input type="text" name="program" value="<?= htmlspecialchars($config['program'] ?? '/usr/bin/ntlm_auth --helper-protocol=squid-2.5-ntlmssp') ?>">
            </div>
            <div class="form-group">
                <label>Children</label>
                <input type="number" name="children" value="<?= (int)($config['children'] ?? 10) ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Domain</label>
                <input type="text" name="domain" value="<?= htmlspecialchars($config['domain'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>DC</label>
                <input type="text" name="dc" value="<?= htmlspecialchars($config['dc'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Backup DC</label>
            <input type="text" name="backup_dc" value="<?= htmlspecialchars($config['backup_dc'] ?? '') ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
    <?php if (!empty($winbind['raw'])): ?>
    <div class="panel" style="margin-top: 16px;">
        <div class="panel-header"><h3>Winbind Status</h3></div>
        <div class="panel-body"><pre class="code-block"><?= htmlspecialchars($winbind['raw']) ?></pre></div>
    </div>
    <?php endif; ?>
    <?php if (!empty($domainInfo)): ?>
    <div class="panel" style="margin-top: 16px;">
        <div class="panel-header"><h3>Domain Info</h3></div>
        <div class="panel-body"><pre class="code-block"><?= htmlspecialchars($domainInfo) ?></pre></div>
    </div>
    <?php endif; ?>
</div>
