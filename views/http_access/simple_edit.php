<div class="page-header">
    <h2>Edit access rule</h2>
    <a href="/http_access" class="btn btn-secondary">← Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/http_access/update">
            <?= View::csrf() ?>
            <input type="hidden" name="id" value="<?= $rule['id'] ?>">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" required value="<?= htmlspecialchars(PolicyAclKind::ruleTitle($rule) === ('Rule #' . $rule['id']) ? '' : PolicyAclKind::ruleTitle($rule)) ?>" placeholder="Rule name">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Initiator</label>
                    <select name="from[]" multiple size="8">
                        <?php foreach ($fromLists as $item): ?>
                        <option value="<?= htmlspecialchars($item['name']) ?>" <?= in_array($item['name'], $parsed['from'], true) ? 'selected' : '' ?>>
                            <?= htmlspecialchars(PolicyAclKind::label($item, true)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Traffic filter</label>
                    <select name="to[]" multiple size="8">
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
                    <label class="acl-chip" style="margin-top:12px;">
                        <input type="checkbox" name="enabled" value="1" <?= !isset($rule['enabled']) || (int)$rule['enabled'] === 1 ? 'checked' : '' ?>>
                        <span>Rule is on</span>
                    </label>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="/http_access" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
