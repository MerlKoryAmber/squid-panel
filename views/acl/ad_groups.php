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
        <p>Domain join / winbind <strong>not required</strong>. List = LDAP + the same keytab under <code>/etc/squid/*.keytab</code> (upload it on Kerberos page if the live helper still uses <code>/etc/krb5.keytab</code>). LDAP host = Kerberos KDC field, else the realm name. Base DN from realm. Manual name still works if LDAP list fails.</p>
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
            <p style="color:var(--ir-text-muted); font-size:0.82rem;"><?= count($listedGroups) ?> groups from LDAP. Already imported are checked and disabled.</p>
            <div style="max-height:420px; overflow:auto; border:1px solid var(--ir-border, #ddd); padding:8px; margin-bottom:12px;">
                <?php foreach ($listedGroups as $g):
                    $g = (string)$g;
                    $key = strtolower($g);
                    $have = $imported[$key] ?? null;
                    ?>
                <label class="acl-chip" style="display:flex; gap:8px; align-items:center; margin:4px 0;">
                    <input type="checkbox" name="groups[]" value="<?= $h($g) ?>" <?= $have ? 'checked disabled' : '' ?> <?= empty($isAdmin) ? 'disabled' : '' ?>>
                    <span><?= $h($g) ?></span>
                    <?php if ($have): ?>
                    <a href="/acl/edit?id=<?= (int)$have['id'] ?>" style="font-size:0.8rem;"><?= $h($have['name'] ?? '') ?></a>
                    <?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label>Or type a group name (sAMAccountName / CN)</label>
                <input type="text" name="manual" placeholder="ProxyUsers" <?= empty($isAdmin) ? 'readonly' : '' ?>>
            </div>
            <?php if (!empty($isAdmin)): ?>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create ACLs</button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>
