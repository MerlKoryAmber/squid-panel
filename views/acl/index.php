<div class="page-header">
    <h2>Access Control Lists</h2>
    <div>
        <a href="/acl/ad-groups" class="btn btn-secondary">AD groups</a>
        <a href="/acl/create" class="btn btn-primary">+ Add ACL</a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>ACLs</h3>
        <span class="subtitle"><?= count($acls) ?> defined</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($acls)): ?>
        <div class="empty-state">
            <h4>No ACLs configured</h4>
            <p>Create ACLs to define traffic groups for filtering.</p>
        </div>
        <?php else: ?>
        <table class="data-table js-col-sort" data-sort-col="name" data-sort-dir="asc">
            <thead>
                <tr>
                    <th class="js-sort" data-col="name" role="button" tabindex="0" aria-sort="ascending">Name</th>
                    <th class="js-sort" data-col="type" role="button" tabindex="0" aria-sort="none">Type</th>
                    <th class="js-sort" data-col="values" role="button" tabindex="0" aria-sort="none">Values</th>
                    <th style="width:180px; white-space:nowrap;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($acls as $acl): ?>
                <?php
                if (($acl['storage'] ?? 'inline') === 'file') {
                    $valuesText = (int)($acl['list_count'] ?? 0) . ' values · ' . AclListFile::livePath($acl['name']);
                    $valuesSort = sprintf('%08d', (int)($acl['list_count'] ?? 0)) . ' ' . $valuesText;
                } else {
                    $vals = json_decode($acl['entries'] ?? $acl['values'] ?? '[]', true);
                    if (!is_array($vals)) {
                        $vals = [];
                    }
                    $valuesText = implode(', ', array_slice($vals, 0, 5)) . (count($vals) > 5 ? '...' : '');
                    $valuesSort = $valuesText;
                }
                ?>
                <tr>
                    <td data-col="name" data-sort="<?= htmlspecialchars($acl['name'], ENT_QUOTES) ?>"><strong><?= htmlspecialchars($acl['name']) ?></strong>
                        <?php if (!empty($acl['group_name'])): ?>
                        <div style="font-size:0.95rem; color:var(--ir-text-muted);"><?= htmlspecialchars($acl['group_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td data-col="type" data-sort="<?= htmlspecialchars($acl['type'], ENT_QUOTES) ?>">
                        <span class="badge badge-default"><?= htmlspecialchars($acl['type']) ?></span>
                        <?php if (($acl['storage'] ?? 'inline') === 'file'): ?>
                        <span class="badge badge-info">file</span>
                        <?php endif; ?>
                    </td>
                    <td data-col="values" data-sort="<?= htmlspecialchars($valuesSort, ENT_QUOTES) ?>" style="font-size:1.05rem; color:var(--ir-text-secondary);">
                        <?= htmlspecialchars($valuesText) ?>
                    </td>
                    <td>
                        <a href="/acl/edit?id=<?= $acl['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <form method="POST" action="/acl/delete" style="display:inline" data-confirm="Delete list <?= htmlspecialchars($acl['name'], ENT_QUOTES) ?>?">
                            <?= View::csrf() ?>
                            <input type="hidden" name="id" value="<?= $acl['id'] ?>">
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
<script src="<?= htmlspecialchars(View::asset('/assets/js/table-sort.js')) ?>"></script>
