<div class="page-header">
    <h2><?= $acl ? 'Edit ACL' : 'Create ACL' ?></h2>
</div>

<div class="panel">
    <form method="POST" action="<?= $acl ? '/acl/update' : '/acl/store' ?>">
        <?= View::csrf() ?>
        <?php if ($acl): ?><input type="hidden" name="id" value="<?= $acl['id'] ?>"><?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($acl['name'] ?? '') ?>" <?= $acl ? 'readonly' : '' ?> required pattern="[a-zA-Z0-9_-]+">
            </div>
            <div class="form-group">
                <label>Type</label>
                <select name="type" <?= $acl ? 'disabled' : '' ?> required>
                    <?php foreach ($types as $key => $label): ?>
                    <option value="<?= $key ?>" <?= ($acl['type'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($acl): ?><input type="hidden" name="type" value="<?= $acl['type'] ?>"><?php endif; ?>
            </div>
        </div>

        <div class="form-group">
            <label>Values (one per line)</label>
            <textarea name="values" rows="6" required><?= htmlspecialchars(implode("
", json_decode($acl['entries'] ?? '[]', true) ?: [])) ?></textarea>
            <div class="help-text">Enter one value per line. For IPs: 192.168.1.0/24. For time: MTWHF 08:00-18:00</div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Group</label>
                <input type="text" name="group_name" value="<?= htmlspecialchars($acl['group_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Description</label>
                <input type="text" name="description" value="<?= htmlspecialchars($acl['description'] ?? '') ?>">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="/acl" class="btn">Cancel</a>
        </div>
    </form>
</div>
