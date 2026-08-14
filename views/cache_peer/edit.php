<div class="page-header">
    <h2><?= isset($peer) ? 'Edit' : 'Add' ?> Cache Peer</h2>
    <a href="/peers" class="btn btn-secondary">← Back to Peers</a>
</div>

<div class="card">
    <div class="card-header">
        <h3><?= isset($peer) ? 'Edit ' . htmlspecialchars($peer['name']) : 'New Peer' ?></h3>
    </div>
    <div class="card-body">
        <form method="POST" action="/peers/<?= isset($peer) ? 'update' : 'store' ?>">
            <?= View::csrf() ?>
            <?php if (isset($peer)): ?><input type="hidden" name="id" value="<?= $peer['id'] ?>"><?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($peer['name'] ?? '') ?>" placeholder="e.g. ksmg" required>
                </div>
                <div class="form-group">
                    <label>Hostname / IP</label>
                    <input type="text" name="hostname" value="<?= htmlspecialchars($peer['hostname'] ?? '') ?>" placeholder="e.g. 172.26.13.230" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Type</label>
                    <select name="peer_type" required>
                        <option value="parent" <?= ($peer['peer_type'] ?? '') === 'parent' ? 'selected' : '' ?>>parent</option>
                        <option value="sibling" <?= ($peer['peer_type'] ?? '') === 'sibling' ? 'selected' : '' ?>>sibling</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Port</label>
                    <input type="number" name="port" value="<?= $peer['port'] ?? 3128 ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>Options</label>
                <input type="text" name="options" value="<?= htmlspecialchars($peer['options'] ?? '') ?>" placeholder="e.g. no-query proxy-only login=PASSTHRU">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="active" <?= ($peer['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="disabled" <?= ($peer['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Peer</button>
                <a href="/peers" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
