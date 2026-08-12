<div class="page-header">
    <h2>Global Routing Rules</h2>
    <a href="/peers" class="btn">← Back to Peers</a>
</div>

<div class="panel">
    <div class="panel-header">
        <h3>Direct Routing Directives</h3>
    </div>
    <div class="panel-body">
        <p class="text-muted" style="margin-bottom: 16px;">
            These directives control when Squid uses direct connections vs parent/sibling peers.
            Rules are evaluated in order.
        </p>

        <form method="POST" action="/peers/routing/update">
            <?= View::csrf() ?>

            <table class="data-table" id="routingTable">
                <thead>
                    <tr>
                        <th>Directive</th>
                        <th>Action</th>
                        <th>ACL</th>
                        <th>Description</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="routingBody">
                    <?php foreach ($rules as $i => $rule): ?>
                    <tr>
                        <td>
                            <select name="directive[]" required>
                                <option value="never_direct" <?= $rule['directive'] === 'never_direct' ? 'selected' : '' ?>>never_direct</option>
                                <option value="always_direct" <?= $rule['directive'] === 'always_direct' ? 'selected' : '' ?>>always_direct</option>
                                <option value="prefer_direct" <?= $rule['directive'] === 'prefer_direct' ? 'selected' : '' ?>>prefer_direct</option>
                            </select>
                        </td>
                        <td>
                            <select name="action[]" required>
                                <option value="allow" <?= $rule['action'] === 'allow' ? 'selected' : '' ?>>allow</option>
                                <option value="deny" <?= $rule['action'] === 'deny' ? 'selected' : '' ?>>deny</option>
                            </select>
                        </td>
                        <td>
                            <select name="acl_name[]" required>
                                <option value="">Select ACL...</option>
                                <?php foreach ($acls as $acl): ?>
                                <option value="<?= htmlspecialchars($acl['name']) ?>" <?= $rule['acl_name'] === $acl['name'] ? 'selected' : '' ?>><?= htmlspecialchars($acl['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="text-muted" style="font-size: 12px;">
                            <?php if ($rule['directive'] === 'never_direct'): ?>
                                Never use direct connection for matching requests
                            <?php elseif ($rule['directive'] === 'always_direct'): ?>
                                Always use direct connection for matching requests
                            <?php else: ?>
                                Prefer direct connection for matching requests
                            <?php endif; ?>
                        </td>
                        <td><button type="button" class="btn-sm btn-danger" onclick="this.closest('tr').remove()">Remove</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (Auth::isAdmin()): ?>
            <div style="margin-top: 16px; display: flex; gap: 8px;">
                <button type="button" class="btn" onclick="addRoutingRow()">+ Add Rule</button>
                <button type="submit" class="btn btn-primary">Save Rules</button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="panel" style="margin-top: 16px;">
    <div class="panel-header"><h3>Directive Reference</h3></div>
    <div class="panel-body">
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; font-size: 13px;">
            <div>
                <strong style="color: var(--kimi-color-text-primary);">never_direct</strong>
                <p class="text-muted">Forces matching requests to go through a parent proxy. Use when all traffic must be proxied.</p>
                <code class="code-inline">never_direct allow local_net</code>
            </div>
            <div>
                <strong style="color: var(--kimi-color-text-primary);">always_direct</strong>
                <p class="text-muted">Forces matching requests to bypass all peers and connect directly. Use for local/internal resources.</p>
                <code class="code-inline">always_direct allow local_domains</code>
            </div>
            <div>
                <strong style="color: var(--kimi-color-text-primary);">prefer_direct</strong>
                <p class="text-muted">Prefers direct connection over parent for matching requests, but falls back to parent if direct fails.</p>
                <code class="code-inline">prefer_direct allow trusted_hosts</code>
            </div>
        </div>
    </div>
</div>

<script>
const aclOptions = `<?php foreach ($acls as $acl): ?><option value="<?= htmlspecialchars($acl['name']) ?>"><?= htmlspecialchars($acl['name']) ?></option><?php endforeach; ?>`;

function addRoutingRow() {
    const tbody = document.getElementById('routingBody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select name="directive[]" required>
                <option value="never_direct">never_direct</option>
                <option value="always_direct">always_direct</option>
                <option value="prefer_direct">prefer_direct</option>
            </select>
        </td>
        <td>
            <select name="action[]" required>
                <option value="allow">allow</option>
                <option value="deny">deny</option>
            </select>
        </td>
        <td>
            <select name="acl_name[]" required>
                <option value="">Select ACL...</option>
                ${aclOptions}
            </select>
        </td>
        <td class="text-muted" style="font-size: 12px;">New routing rule</td>
        <td><button type="button" class="btn-sm btn-danger" onclick="this.closest('tr').remove()">Remove</button></td>
    `;
    tbody.appendChild(tr);
}
</script>
