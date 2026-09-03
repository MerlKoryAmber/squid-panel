<?php
$h = function ($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$listed = is_array($listed ?? null) ? $listed : ['ok' => false, 'groups' => [], 'error' => ''];
$listedGroups = is_array($listed['groups'] ?? null) ? $listed['groups'] : [];
$imported = is_array($imported ?? null) ? $imported : [];
?>
<div class="page-header">
    <h2>AD groups</h2>
    <a href="/acl" class="btn btn-secondary">← ACLs</a>
</div>

<?php if (!empty($flashError)): ?>
<div class="alert alert-danger"><?= $h($flashError) ?></div>
<?php endif; ?>
<?php if (!empty($flashSuccess)): ?>
<div class="alert alert-success"><?= $h($flashSuccess) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>How it works</h3></div>
    <div class="card-body" style="font-size:0.9rem; color:var(--ir-text-secondary);">
        <p>Squid checks membership live via <code>ext_kerberos_ldap_group_acl</code>. The panel does not dump user lists into SQLite.</p>
        <p><strong>LDAP settings</strong> below: <strong>simple bind = primary</strong> for group list + Squid helper; <code>gssapi</code>/keytab = reserve. Servers → <code>-S</code> / <code>-l</code>.</p>
        <p>Realm from Kerberos: <code><?= $h(($realm ?? '') !== '' ? $realm : '(empty — set Kerberos realm)') ?></code>.</p>
        <p>Each selected group → ACL <code>ad_*</code> + helper <code>kg_*</code>. Apply writes live <code>squid.conf</code>.</p>
    </div>
</div>

<?php $ldap = is_array($ldap ?? null) ? $ldap : []; ?>
<div class="card">
    <div class="card-header"><h3>LDAP directory</h3></div>
    <div class="card-body">
        <form method="POST" action="/acl/ad-groups/ldap">
            <?= View::csrf() ?>
            <div class="form-group">
                <label>Bind mode</label>
                <select name="bind_mode" <?= empty($isAdmin) ? 'disabled' : '' ?>>
                    <option value="simple" <?= ($ldap['bind_mode'] ?? 'simple') === 'simple' ? 'selected' : '' ?>>Simple bind — LDAP primary</option>
                    <option value="gssapi" <?= ($ldap['bind_mode'] ?? '') === 'gssapi' ? 'selected' : '' ?>>GSSAPI keytab — Kerberos reserve</option>
                </select>
                <p style="color:var(--ir-text-muted); font-size:0.82rem; margin-top:6px;">
                    Primary: LDAP DN+password to pinned DC. Incomplete simple → panel falls back to Kerberos/GSSAPI.
                </p>
            </div>
            <div class="form-group">
                <label>LDAP servers (FQDN, one per line)</label>
                <textarea name="servers" rows="3" placeholder="hdc-01.hci.interros.ru&#10;hdc-02.hci.interros.ru" <?= empty($isAdmin) ? 'readonly' : '' ?>><?= $h($ldap['servers'] ?? '') ?></textarea>
                <p style="color:var(--ir-text-muted); font-size:0.82rem; margin-top:6px;">Pinned DC list (<code>-S</code>). Empty + GSSAPI = DNS SRV.</p>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Port</label>
                    <input type="number" name="port" min="1" max="65535" value="<?= (int)($ldap['port'] ?? 389) ?>" <?= empty($isAdmin) ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                    <label>Use SSL (LDAPS)</label>
                    <select name="use_ssl" <?= empty($isAdmin) ? 'disabled' : '' ?>>
                        <option value="0" <?= empty($ldap['use_ssl']) ? 'selected' : '' ?>>No</option>
                        <option value="1" <?= !empty($ldap['use_ssl']) ? 'selected' : '' ?>>Yes</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Bind DN (simple mode)</label>
                <input type="text" name="bind_dn" value="<?= $h($ldap['bind_dn'] ?? '') ?>" placeholder="CN=squid-ldap,OU=Service,DC=hci,DC=interros,DC=ru" <?= empty($isAdmin) ? 'readonly' : '' ?>>
            </div>
            <div class="form-group">
                <label>Bind password (simple mode)</label>
                <input type="password" name="bind_password" value="" placeholder="<?= !empty($ldap['has_password']) ? '********' : '' ?>" autocomplete="new-password" <?= empty($isAdmin) ? 'readonly' : '' ?>>
                <p style="color:var(--ir-text-muted); font-size:0.82rem; margin-top:6px;">Leave blank to keep current. No spaces/quotes (Squid <code>-p</code>). Stored in <code>spm.db</code> and written into live helper line.</p>
            </div>
            <div class="form-group">
                <label>Base DN (optional)</label>
                <input type="text" name="base_dn" value="<?= $h($ldap['base_dn'] ?? '') ?>" placeholder="DC=hci,DC=interros,DC=ru" <?= empty($isAdmin) ? 'readonly' : '' ?>>
                <p style="color:var(--ir-text-muted); font-size:0.82rem; margin-top:6px;">Empty = build from Kerberos realm.</p>
            </div>
            <?php if (!empty($isAdmin)): ?>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save LDAP &amp; apply</button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Import</h3></div>
    <div class="card-body">
        <?php if (empty($listed['ok'])): ?>
        <div class="alert alert-danger"><?= $h(($listed['error'] ?? '') !== '' ? $listed['error'] : 'LDAP group list failed') ?></div>
        <?php endif; ?>
        <form method="POST" action="/acl/ad-groups/import">
            <?= View::csrf() ?>
            <?php if (!empty($listedGroups)): ?>
            <p style="color:var(--ir-text-muted); font-size:0.82rem;"><?= count($listedGroups) ?> groups from LDAP. Already imported are checked and disabled. Filter hides unmatched names; Create ACLs uses checked groups only.</p>
            <div class="form-group">
                <label for="ad-group-filter">Filter groups (any part of the name)</label>
                <input type="search" id="ad-group-filter" placeholder="sAMAccountName / CN" autocomplete="off" <?= empty($listedGroups) ? 'disabled' : '' ?>>
            </div>
            <div id="ad-group-list" style="max-height:420px; overflow:auto; border:1px solid var(--ir-border, #ddd); padding:8px; margin-bottom:12px;">
                <?php foreach ($listedGroups as $g):
                    $g = (string)$g;
                    $key = strtolower($g);
                    $have = $imported[$key] ?? null;
                    ?>
                <label class="acl-chip js-ad-group" data-filter="<?= $h(mb_strtolower($g)) ?>" style="display:flex; gap:8px; align-items:center; margin:4px 0;">
                    <input type="checkbox" name="groups[]" value="<?= $h($g) ?>" <?= $have ? 'checked disabled' : '' ?> <?= empty($isAdmin) ? 'disabled' : '' ?>>
                    <span><?= $h($g) ?></span>
                    <?php if ($have): ?>
                    <a href="/acl/edit?id=<?= (int)$have['id'] ?>" style="font-size:0.8rem;"><?= $h($have['name'] ?? '') ?></a>
                    <?php endif; ?>
                </label>
                <?php endforeach; ?>
                <p id="ad-group-empty" hidden style="color:var(--ir-text-muted); font-size:0.82rem; margin:8px 0 0;">No groups match the filter.</p>
            </div>
            <?php endif; ?>
            <?php if (!empty($isAdmin)): ?>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create ACLs</button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>
<?php if (!empty($listedGroups)): ?>
<script>
(function () {
    var input = document.getElementById('ad-group-filter');
    var rows = document.querySelectorAll('.js-ad-group');
    var empty = document.getElementById('ad-group-empty');
    if (!input || !rows.length) {
        return;
    }
    function apply() {
        var q = (input.value || '').toLowerCase().trim();
        var n = 0;
        for (var i = 0; i < rows.length; i++) {
            var hay = rows[i].getAttribute('data-filter') || '';
            var show = q === '' || hay.indexOf(q) !== -1;
            rows[i].style.display = show ? 'flex' : 'none';
            if (show) {
                n++;
            }
        }
        if (empty) {
            empty.hidden = n !== 0;
        }
    }
    input.addEventListener('input', apply);
    input.addEventListener('search', apply);
})();
</script>
<?php endif; ?>
