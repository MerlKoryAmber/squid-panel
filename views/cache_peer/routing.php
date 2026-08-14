<div class="page-header">
    <h2>Cache Peer Routing</h2>
    <a href="/peers" class="btn btn-secondary">← Back to Peers</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>Routing Rules</h3>
        <span class="subtitle">never_direct / always_direct</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($rules)): ?>
        <div class="empty-state">
            <h4>No routing rules</h4>
            <p>Define never_direct or always_direct rules.</p>
        </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>ACLs</th>
                    <th style="width:120px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rules as $rule): ?>
                <tr>
                    <td><span class="badge badge-info"><?= $rule['rule_type'] ?></span></td>
                    <td>
                        <?php $acls = json_decode($rule['acls'], true) ?? []; foreach ($acls as $a): ?>
                        <span class="badge badge-default" style="margin-right:4px;"><?= htmlspecialchars($a) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <form method="POST" action="/peers/routing/delete" style="display:inline" onsubmit="return confirm('Delete?')">
                            <?= View::csrf() ?>
                            <input type="hidden" name="id" value="<?= $rule['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Add Routing Rule</h3></div>
    <div class="card-body">
        <form method="POST" action="/peers/routing/store">
            <?= View::csrf() ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Rule Type</label>
                    <select name="rule_type" required>
                        <option value="never_direct">never_direct</option>
                        <option value="always_direct">always_direct</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 2;">
                    <label>ACLs (multiple)</label>
                    <select name="acls[]" multiple size="5" required>
                        <?php foreach ($acls as $acl): ?>
                        <option value="<?= htmlspecialchars($acl['name']) ?>"><?= htmlspecialchars($acl['name']) ?> (<?= $acl['type'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Add Rule</button>
            </div>
        </form>
    </div>
</div>
