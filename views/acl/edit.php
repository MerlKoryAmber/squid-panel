<div class="page-header">
    <h2><?= isset($acl) ? 'Edit' : 'Add' ?> ACL</h2>
    <a href="/acl" class="btn btn-secondary">← Back to ACLs</a>
</div>

<?php
$isFile = isset($acl) && (($acl['storage'] ?? 'inline') === 'file');
$fileTypes = 'dstdomain, srcdomain, dst, src';
$livePath = isset($acl['name']) ? AclListFile::livePath($acl['name']) : '/etc/squid/acl.d/<name>.txt';
?>

<?php if (!empty($installNote)): ?>
<div class="alert alert-success">List file copied to <?= htmlspecialchars(AclListFile::liveDir()) ?>. Save also rewrites live squid.conf after parse.</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3><?= isset($acl) ? 'Edit ' . htmlspecialchars($acl['name']) : 'New ACL' ?></h3>
    </div>
    <div class="card-body">
        <form method="POST" action="/acl/<?= isset($acl) ? 'update' : 'store' ?>">
            <?= View::csrf() ?>
            <?php if (isset($acl)): ?><input type="hidden" name="id" value="<?= $acl['id'] ?>"><?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($acl['name'] ?? '') ?>" placeholder="e.g. cascade_sites" required <?= empty($isAdmin) ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" required <?= empty($isAdmin) ? 'disabled' : '' ?>>
                        <?php $types = ['src','dst','dstdomain','srcdomain','url_regex','urlpath_regex','port','proto','method','time','req_header','rep_header','external','proxy_auth']; ?>
                        <?php foreach ($types as $t): ?>
                        <option value="<?= $t ?>" <?= ($acl['type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="acl-chip" style="display:inline-flex; margin-bottom:8px;">
                    <input type="checkbox" name="storage_file" value="1" <?= $isFile ? 'checked' : '' ?> <?= empty($isAdmin) ? 'disabled' : '' ?>>
                    <span>Large list (Squid file)</span>
                </label>
                <p class="text-muted" style="font-size:0.82rem; margin-bottom:8px;">
                    For <?= htmlspecialchars($fileTypes) ?>. One value per line. From <?= (int)AclListFile::AUTO_FILE_MIN ?> entries the list is stored as a file automatically.
                    Squid then uses one line:
                    <code>acl <?= htmlspecialchars($acl['name'] ?? 'Name') ?> <?= htmlspecialchars($acl['type'] ?? 'dstdomain') ?> "<?= htmlspecialchars($livePath) ?>"</code>
                    Live squid.conf is rewritten on Save (after parse).
                </p>
                <label>Values (one per line)</label>
                <textarea name="entries" rows="<?= $isFile ? 18 : 8 ?>" placeholder=".example.com&#10;.other.org" <?= empty($isAdmin) ? 'readonly' : '' ?>><?php
                    $vals = [];
                    if (isset($acl)) {
                        $vals = json_decode($acl['entries'] ?? $acl['values'] ?? '[]', true);
                        if (!is_array($vals)) {
                            $vals = [];
                        }
                    }
                    echo htmlspecialchars(implode("\n", $vals));
                ?></textarea>
            </div>
            <div class="form-actions">
                <?php if (!empty($isAdmin)): ?>
                <button type="submit" class="btn btn-primary">Save ACL</button>
                <?php endif; ?>
                <a href="/acl" class="btn btn-secondary"><?= !empty($isAdmin) ? 'Cancel' : 'Back' ?></a>
            </div>
        </form>
    </div>
</div>
