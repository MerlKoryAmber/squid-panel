<div class="page-header">
    <h2>Edit Peer Access Rule</h2>
    <a href="/peers/access?peer_id=<?= $rule['peer_id'] ?>" class="btn">← Back to Peer Access</a>
</div>

<div class="panel">
    <div class="panel-header">
        <h3>Edit Rule for <?= htmlspecialchars($peer['hostname']) ?></h3>
        <span class="text-muted">Rule #<?= $rule['id'] ?></span>
    </div>
    <div class="panel-body">
        <form method="POST" action="/peers/access/update">
            <?= View::csrf() ?>
            <input type="hidden" name="id" value="<?= $rule['id'] ?>">
            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label>ACL Entries <span class="text-muted">(space separated, e.g. <code>HCIITVM2127 !CYPInet</code>)</span></label>
                    <input type="text" name="acl_entries" value="<?= htmlspecialchars($rule['acl_entries']) ?>" required style="width: 100%; font-family: monospace;">
                    <small class="text-muted">Multiple ACLs are combined with AND logic</small>
                </div>
                <div class="form-group">
                    <label>Action</label>
                    <select name="action" required>
                        <option value="allow" <?= $rule['action'] === 'allow' ? 'selected' : '' ?>>allow — route to this peer</option>
                        <option value="deny" <?= $rule['action'] === 'deny' ? 'selected' : '' ?>>deny — bypass this peer</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="/peers/access?peer_id=<?= $rule['peer_id'] ?>" class="btn">Cancel</a>
            </div>
        </form>

        <div style="margin-top: 24px; padding: 16px; background: var(--kimi-color-surface-secondary); border-radius: var(--radius-md);">
            <h4 style="margin-bottom: 8px;">Preview</h4>
            <code class="code-inline">cache_peer_access <?= htmlspecialchars($peer['hostname']) ?> <?= $rule['action'] ?> <?= htmlspecialchars($rule['acl_entries']) ?></code>
        </div>
    </div>
</div>
