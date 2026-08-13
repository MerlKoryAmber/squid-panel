<div class="page-header">
    <h2>HTTP Access Rules</h2>
    <?php if (Auth::isAdmin()): ?>
    <button class="btn btn-primary" onclick="document.getElementById('createForm').style.display='block'">+ Add Rule</button>
    <?php endif; ?>
</div>

<div id="createForm" class="panel" style="display:none; margin-bottom: 16px;">
    <div class="panel-header"><h3>New HTTP Access Rule</h3></div>
    <div class="panel-body">
        <form method="POST" action="/http_access/store">
            <?= View::csrf() ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Action</label>
                    <select name="action" required>
                        <option value="allow">Allow</option>
                        <option value="deny">Deny</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>ACLs (hold Ctrl/Cmd to select multiple)</label>
                    <select name="acls[]" multiple size="5" required>
                        <?php foreach ($acls as $acl): ?>
                        <option value="<?= htmlspecialchars($acl['name']) ?>"><?= htmlspecialchars($acl['name']) ?> (<?= htmlspecialchars($acl['type']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <input type="text" name="description" placeholder="Optional description">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" class="btn" onclick="document.getElementById('createForm').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="panel">
    <table class="data-table">
        <thead>
            <tr><th>Order</th><th>Action</th><th>ACLs</th><th>Description</th><?php if (Auth::isAdmin()): ?><th>Actions</th><?php endif; ?></tr>
        </thead>
        <tbody>
            <?php foreach ($rules as $rule): ?>
            <tr>
                <td><?= $rule['sort_order'] ?></td>
                <td><span class="badge badge-<?= $rule['action'] === 'allow' ? 'success' : 'danger' ?>"><?= htmlspecialchars($rule['action']) ?></span></td>
                <td>
                    <?php foreach (json_decode($rule['acls'], true) ?? [] as $aclName): ?>
                    <span class="badge"><?= htmlspecialchars($aclName) ?></span>
                    <?php endforeach; ?>
                </td>
                <td><?= htmlspecialchars($rule['description'] ?? '') ?></td>
                <?php if (Auth::isAdmin()): ?>
                <td>
                    <form method="POST" action="/http_access/delete" style="display:inline" onsubmit="return confirm('Delete this rule?')">
                        <?= View::csrf() ?>
                        <input type="hidden" name="id" value="<?= $rule['id'] ?>">
                        <button type="submit" class="btn-sm btn-danger">Delete</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
