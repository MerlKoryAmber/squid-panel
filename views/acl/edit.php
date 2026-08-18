<div class="page-header">
    <h2><?= isset($acl) ? 'Edit' : 'Add' ?> ACL</h2>
    <a href="/acl" class="btn btn-secondary">← Back to ACLs</a>
</div>

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
                    <input type="text" name="name" value="<?= htmlspecialchars($acl['name'] ?? '') ?>" placeholder="e.g. localnet" required <?= empty($isAdmin) ? 'readonly' : '' ?>>
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
                <label>Values (one per line)</label>
                <textarea name="entries" rows="6" placeholder="192.168.1.0/24&#10;10.0.0.0/8" <?= empty($isAdmin) ? 'readonly' : '' ?>><?php
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
