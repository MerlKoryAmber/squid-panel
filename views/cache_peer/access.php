<div class="page-header">
    <h2>Peer Access Rules: <?= htmlspecialchars($peer['hostname']) ?></h2>
    <a href="/peers" class="btn">← Back to Peers</a>
</div>

<div class="panel">
    <div class="panel-header">
        <h3>cache_peer_access rules</h3>
        <span class="text-muted">Controls which requests are sent to this peer</span>
    </div>
    <div class="panel-body">
        <p class="text-muted" style="margin-bottom: 12px;">
            Rules are evaluated in order (first match). Use <code>allow</code> to route matching requests to this peer, 
            <code>deny</code> to bypass it.
        </p>

        <table class="data-table" id="accessRulesTable">
            <thead>
                <tr><th>Order</th><th>ACL</th><th>Action</th><th>Generated Line</th><?php if (Auth::isAdmin()): ?><th>Actions</th><?php endif; ?></tr>
            </thead>
            <tbody id="rulesTbody">
                <?php foreach ($rules as $rule): ?>
                <tr data-id="<?= $rule['id'] ?>">
                    <td class="drag-handle" style="cursor: grab; color: var(--kimi-color-text-quaternary);">⋮⋮</td>
                    <td><code><?= ($rule['negated'] ?? 0) ? '!' : '' ?><?= htmlspecialchars($rule['acl_name']) ?></code></td>
                    <td><span class="badge badge-<?= $rule['action'] === 'allow' ? 'success' : 'danger' ?>"><?= $rule['action'] ?></span></td>
                    <td><code class="code-inline">cache_peer_access <?= htmlspecialchars($peer['hostname']) ?> <?= $rule['action'] ?> <?= ($rule['negated'] ?? 0) ? '!' : '' ?><?= htmlspecialchars($rule['acl_name']) ?></code></td>
                    <?php if (Auth::isAdmin()): ?>
                    <td>
                        <form method="POST" action="/peers/access/delete" style="display:inline" onsubmit="return confirm('Delete this rule?')">
                            <?= View::csrf() ?>
                            <input type="hidden" name="id" value="<?= $rule['id'] ?>">
                            <input type="hidden" name="peer_id" value="<?= $peer['id'] ?>">
                            <button type="submit" class="btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (Auth::isAdmin()): ?>
        <div style="margin-top: 16px;">
            <button class="btn btn-primary" onclick="document.getElementById('addRuleForm').style.display='block'">+ Add Rule</button>
            <button class="btn" id="saveOrderBtn" style="display:none;" onclick="saveOrder()">Save Order</button>
        </div>

        <form method="POST" action="/peers/access/store" id="addRuleForm" style="display:none; margin-top: 16px; padding: 16px; background: var(--kimi-color-surface-secondary); border-radius: var(--radius-md);">
            <?= View::csrf() ?>
            <input type="hidden" name="peer_id" value="<?= $peer['id'] ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>ACL</label>
                    <select name="acl_name" required>
                        <option value="">Select ACL...</option>
                        <?php foreach ($acls as $acl): ?>
                        <option value="<?= htmlspecialchars($acl['name']) ?>"><?= htmlspecialchars($acl['name']) ?> (<?= $acl['type'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Action</label>
                    <select name="action" required>
                        <option value="allow">allow — route to this peer</option>
                        <option value="deny">deny — bypass this peer</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Add Rule</button>
                <button type="button" class="btn" onclick="document.getElementById('addRuleForm').style.display='none'">Cancel</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="panel" style="margin-top: 16px;">
    <div class="panel-header"><h3>Peer Configuration Preview</h3></div>
    <div class="panel-body">
        <pre class="code-block">cache_peer <?= htmlspecialchars($peer['hostname']) ?> <?= $peer['peer_type'] ?> <?= $peer['http_port'] ?><?= $peer['icp_port'] ? ' ' . $peer['icp_port'] : '' ?><?= $peer['proxy_only'] ? ' proxy-only' : '' ?><?= $peer['no_query'] ? ' no-query' : '' ?><?= $peer['weight'] ? ' weight=' . $peer['weight'] : '' ?><?= $peer['login'] ? ' login=' . $peer['login'] : '' ?>
<?php foreach ($rules as $rule): ?>cache_peer_access <?= htmlspecialchars($peer['hostname']) ?> <?= $rule['action'] ?> <?= ($rule['negated'] ?? 0) ? '!' : '' ?><?= htmlspecialchars($rule['acl_name']) ?>
<?php endforeach; ?></pre>
    </div>
</div>

<script>
// Simple drag-and-drop reordering
let draggedRow = null;
let orderChanged = false;

const tbody = document.getElementById('rulesTbody');
if (tbody) {
    tbody.querySelectorAll('tr').forEach(row => {
        row.draggable = true;
        row.querySelector('.drag-handle').addEventListener('mousedown', () => row.draggable = true);

        row.addEventListener('dragstart', e => {
            draggedRow = row;
            row.style.opacity = '0.5';
        });
        row.addEventListener('dragend', e => {
            row.style.opacity = '1';
            draggedRow = null;
            if (orderChanged) {
                document.getElementById('saveOrderBtn').style.display = 'inline-flex';
            }
        });
        row.addEventListener('dragover', e => {
            e.preventDefault();
            if (draggedRow && draggedRow !== row) {
                const rect = row.getBoundingClientRect();
                const mid = rect.top + rect.height / 2;
                if (e.clientY < mid) {
                    tbody.insertBefore(draggedRow, row);
                } else {
                    tbody.insertBefore(draggedRow, row.nextSibling);
                }
                orderChanged = true;
            }
        });
    });
}

function saveOrder() {
    const rows = tbody.querySelectorAll('tr');
    const order = Array.from(rows).map(r => r.dataset.id);

    fetch('/peers/access/reorder', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'peer_id=<?= $peer['id'] ?>&' + order.map((id, i) => 'order[' + i + ']=' + id).join('&') + '&spm_csrf_token=' + encodeURIComponent(document.querySelector('input[name="spm_csrf_token"]')?.value || '')
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('saveOrderBtn').style.display = 'none';
            orderChanged = false;
            location.reload();
        }
    });
}
</script>
