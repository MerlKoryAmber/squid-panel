<div class="page-header">
    <h2>Cache Peers</h2>
    <a href="/peers/create" class="btn btn-primary">+ Add Peer</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>Peers</h3>
        <span class="subtitle">Upstream proxy configuration</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($peers)): ?>
        <div class="empty-state">
            <h4>No peers configured</h4>
            <p>Add cache peers to enable upstream proxy routing.</p>
        </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Hostname</th>
                    <th>Type</th>
                    <th>Port</th>
                    <th>Options</th>
                    <th>Status</th>
                    <th style="width:200px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($peers as $peer): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($peer['name']) ?></strong></td>
                    <td><code class="code-inline"><?= htmlspecialchars($peer['hostname']) ?></code></td>
                    <td><?= htmlspecialchars($peer['peer_type']) ?></td>
                    <td><?= $peer['port'] ?></td>
                    <td style="font-size:0.78rem; color:var(--ir-text-muted);"><?= htmlspecialchars($peer['options'] ?? '') ?></td>
                    <td>
                        <span class="badge badge-<?= ($peer['status'] ?? 'active') === 'active' ? 'success' : 'default' ?>">
                            <?= $peer['status'] ?? 'active' ?>
                        </span>
                    </td>
                    <td>
                        <a href="/peers/access?peer_id=<?= $peer['id'] ?>" class="btn btn-sm btn-secondary">Access</a>
                        <a href="/peers/edit?id=<?= $peer['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                        <form method="POST" action="/peers/delete" style="display:inline" onsubmit="return confirm('Delete peer <?= htmlspecialchars($peer['name']) ?>?')">
                            <?= View::csrf() ?>
                            <input type="hidden" name="id" value="<?= $peer['id'] ?>">
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
    <div class="card-header"><h3>Preview</h3></div>
    <div class="card-body">
        <div class="code-block"><?php foreach ($peers as $peer): ?>cache_peer <?= htmlspecialchars($peer['hostname']) ?> <?= $peer['peer_type'] ?> <?= $peer['port'] ?> 0 <?= htmlspecialchars($peer['options'] ?? '') ?> name=<?= htmlspecialchars($peer['name']) ?>
<?php endforeach; ?></div>
    </div>
</div>
