<div class="page-header">
    <h2>Edit HTTP rule</h2>
    <a href="/http_access" class="btn btn-secondary">← Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/http_access/update">
            <?= View::csrf() ?>
            <input type="hidden" name="id" value="<?= $rule['id'] ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>From</label>
                    <select name="from[]" multiple size="6">
                        <?php foreach ($fromLists as $item): ?>
                        <option value="<?= htmlspecialchars($item['name']) ?>" <?= in_array($item['name'], $parsed['from'], true) ? 'selected' : '' ?>>
                            <?= htmlspecialchars(PolicyAclKind::label($item, true)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>To</label>
                    <select name="to[]" multiple size="6">
                        <?php foreach ($toLists as $item): ?>
                        <option value="<?= htmlspecialchars($item['name']) ?>" <?= in_array($item['name'], $parsed['to'], true) ? 'selected' : '' ?>>
                            <?= htmlspecialchars(PolicyAclKind::label($item, true)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Action</label>
                    <select name="action" required>
                        <option value="allow" <?= $rule['action'] === 'allow' ? 'selected' : '' ?>>Allow</option>
                        <option value="deny" <?= $rule['action'] === 'deny' ? 'selected' : '' ?>>Deny</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Note</label>
                <input type="text" name="description" value="<?= htmlspecialchars($rule['description'] ?? '') ?>">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="/http_access" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
