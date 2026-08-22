<div class="page-header">
    <h2>Edit HTTP Access Rule</h2>
    <a href="/http_access" class="btn btn-secondary">← Back to Rules</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>Rule #<?= $rule['id'] ?></h3>
    </div>
    <div class="card-body">
        <form method="POST" action="/http_access/update">
            <?= View::csrf() ?>
            <input type="hidden" name="id" value="<?= $rule['id'] ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Action</label>
                    <select name="action" required>
                        <option value="allow" <?= $rule['action'] === 'allow' ? 'selected' : '' ?>>Allow</option>
                        <option value="deny" <?= $rule['action'] === 'deny' ? 'selected' : '' ?>>Deny</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 2;">
                    <label>ACLs (hold Ctrl/Cmd to select multiple)</label>
                    <select name="acls[]" multiple size="16" required class="acl-pick">
                        <?php
                        $selectedAcls = json_decode($rule['acls'], true) ?? [];
                        foreach ($acls as $acl):
                        ?>
                        <option value="<?= htmlspecialchars($acl['name']) ?>" <?= in_array($acl['name'], $selectedAcls) ? 'selected' : '' ?>>
                            <?= htmlspecialchars(PolicyAclKind::selectOption($acl)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <input type="text" name="description" value="<?= htmlspecialchars($rule['description'] ?? '') ?>" placeholder="Optional description">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="/http_access" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
