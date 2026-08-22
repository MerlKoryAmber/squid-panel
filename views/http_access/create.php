<div class="page-header">
    <h2>Add HTTP Access Rule</h2>
    <a href="/http_access" class="btn btn-secondary">← Back to Rules</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>New rule</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="/http_access/store">
            <?= View::csrf() ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Action</label>
                    <select name="action" required>
                        <option value="allow">Allow</option>
                        <option value="deny" selected>Deny</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 2;">
                    <label>ACLs (hold Ctrl/Cmd to select multiple)</label>
                    <select name="acls[]" multiple size="6" required>
                        <?php foreach ($acls as $acl): ?>
                        <option value="<?= htmlspecialchars($acl['name']) ?>">
                            <?= htmlspecialchars($acl['name']) ?> (<?= htmlspecialchars($acl['type']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <input type="text" name="description" value="" placeholder="Optional description">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="/http_access" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
