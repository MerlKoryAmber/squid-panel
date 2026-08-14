<div class="page-header">
    <h2>Edit Peer Access Rule</h2>
    <a href="/peers/access?peer_id=<?= $rule['peer_id'] ?>" class="btn btn-secondary">← Back</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>Edit Rule for <?= htmlspecialchars($peer['hostname']) ?></h3>
        <span class="subtitle">Rule #<?= $rule['id'] ?></span>
    </div>
    <div class="card-body">
        <form method="POST" action="/peers/access/update">
            <?= View::csrf() ?>
            <input type="hidden" name="id" value="<?= $rule['id'] ?>">
            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label>ACL Entries <span class="text-muted">(space separated)</span></label>
                    <input type="text" name="acl_entries" value="<?= htmlspecialchars($rule['acl_entries']) ?>" required style="font-family: monospace;">
                    <small class="text-muted">Multiple ACLs combined with AND logic</small>
                </div>
                <div class="form-group">
                    <label>Action</label>
                    <select name="action" required>
                        <option value="allow" <?= $rule['action'] === 'allow' ? 'selected' : '' ?>>allow — route to peer</option>
                        <option value="deny" <?= $rule['action'] === 'deny' ? 'selected' : '' ?>>deny — bypass peer</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="/peers/access?peer_id=<?= $rule['peer_id'] ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

        <div style="margin-top: var(--space-xl); padding: var(--space-lg); background: var(--ir-bg); border-radius: var(--radius-md);">
            <h4 style="margin-bottom: var(--space-sm); font-size: 0.85rem; color: var(--ir-text-secondary); text-transform: uppercase; letter-spacing: 0.05em;">Preview</h4>
            <code class="code-inline">cache_peer_access <?= htmlspecialchars($peer['hostname']) ?> <?= $rule['action'] ?> <?= htmlspecialchars($rule['acl_entries']) ?></code>
        </div>
    </div>
</div>
