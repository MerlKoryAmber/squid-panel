<div class="page-header">
    <h2>Cache Peer Access — <?= htmlspecialchars($peer['name']) ?></h2>
    <div>
        <a href="/peers" class="btn btn-secondary">← All Peers</a>
        <button class="btn btn-primary" onclick="document.getElementById('addRuleForm').style.display='block'">+ Add Rule</button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Access Rules</h3>
        <span class="subtitle">Drag to reorder · <?= count($rules) ?> rule(s)</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($rules)): ?>
        <div class="empty-state">
            <h4>No access rules</h4>
            <p>Rules control which traffic is routed through this peer.</p>
        </div>
        <?php else: ?>
        <table class="data-table" id="peerAccessTable">
            <thead>
                <tr>
                    <th style="width:40px;"></th>
                    <th>ACL Entries</th>
                    <th>Action</th>
                    <th>Preview</th>
                    <th style="width:180px; white-space:nowrap;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rules as $rule): ?>
                <tr data-id="<?= $rule['id'] ?>">
                    <td class="drag-handle">⋮⋮</td>
                    <td><code class="code-inline"><?= htmlspecialchars($rule['acl_entries']) ?></code></td>
                    <td>
                        <span class="badge badge-<?= $rule['action'] === 'allow' ? 'success' : 'danger' ?>">
                            <?= $rule['action'] ?>
                        </span>
                    </td>
                    <td><code class="code-inline" style="font-size:0.75rem;">cache_peer_access <?= htmlspecialchars($peer['hostname']) ?> <?= $rule['action'] ?> <?= htmlspecialchars($rule['acl_entries']) ?></code></td>
                    <td>
                        <a href="/peers/access/edit?id=<?= $rule['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <form method="POST" action="/peers/access/delete" style="display:inline" onsubmit="return confirm('Delete this rule?')">
                            <?= View::csrf() ?>
                            <input type="hidden" name="id" value="<?= $rule['id'] ?>">
                            <input type="hidden" name="peer_id" value="<?= $peer['id'] ?>">
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

<div class="card" id="addRuleForm" style="display:none;">
    <div class="card-header"><h3>Add Access Rule</h3></div>
    <div class="card-body">
        <form method="POST" action="/peers/access/store">
            <?= View::csrf() ?>
            <input type="hidden" name="peer_id" value="<?= $peer['id'] ?>">
            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label>ACL Entries <span class="text-muted">(space separated)</span></label>
                    <input type="text" name="acl_entries" placeholder="e.g. HCIITVM2127 !CYPInet" required style="font-family: monospace;">
                    <small class="text-muted">Multiple ACLs combined with AND logic</small>
                </div>
                <div class="form-group">
                    <label>Action</label>
                    <select name="action" required>
                        <option value="allow">allow — route to peer</option>
                        <option value="deny">deny — bypass peer</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Add Rule</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addRuleForm').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Preview</h3></div>
    <div class="card-body">
        <div class="code-block"><?php foreach ($rules as $rule): ?>cache_peer_access <?= htmlspecialchars($peer['hostname']) ?> <?= $rule['action'] ?> <?= htmlspecialchars($rule['acl_entries']) ?>
<?php endforeach; ?></div>
    </div>
</div>

<script src="/assets/js/sortable.js"></script>
<script>
const table = document.getElementById('peerAccessTable');
if (table) {
    new Sortable(table.querySelector('tbody'), {
        handle: '.drag-handle',
        animation: 150,
        onEnd: function() {
            const rows = table.querySelectorAll('tbody tr');
            const order = Array.from(rows).map(r => r.dataset.id);
            fetch('/peers/access/reorder', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?? '' ?>' },
                body: JSON.stringify({ peer_id: <?= $peer['id'] ?>, order: order })
            }).then(() => location.reload());
        }
    });
}
</script>
