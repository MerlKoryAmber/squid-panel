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
        <p>Squid checks membership live via <code>ext_kerberos_ldap_group_acl</code> (LDAP + Kerberos). The panel does not dump user lists into SQLite.</p>
        <p>Each selected group becomes an ACL <code>ad_*</code> plus helper <code>kg_*</code> with <code>-g GROUP@REALM</code> (non-ASCII: <code>-t</code> hex). Nested AD groups: <code>-m 5</code>.</p>
        <p>Realm from Kerberos settings: <code><?= $h(($realm ?? '') !== '' ? $realm : '(empty — set Kerberos realm)') ?></code>.</p>
        <p>Domain join / winbind <strong>not required</strong>. List = LDAP + the same keytab under <code>/etc/squid/*.keytab</code> (upload it on Kerberos page if the live helper still uses <code>/etc/krb5.keytab</code>). LDAP host = Kerberos KDC field, else the realm name. Base DN from realm. Filter the list, then tick groups to import.</p>
        <p>Apply: HTTP Access / Cascade. Live <code>squid.conf</code> is not overwritten. Squid helper also uses the keytab (<code>KRB5_KTNAME</code> if not default). The keytab account must be allowed to read groups in AD.</p>
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
