<div class="page-header">
    <h2>Cache Peers</h2>
    <?php if (Auth::isAdmin()): ?>
    <a href="/peers/create" class="btn btn-primary">+ Add Peer</a>
    <?php endif; ?>
</div>

<div class="panel">
    <table class="data-table">
        <thead>
            <tr><th>Hostname</th><th>Type</th><th>HTTP Port</th><th>Options</th><th>Login</th><th>Access Rules</th><?php if (Auth::isAdmin()): ?><th>Actions</th><?php endif; ?></tr>
        </thead>
        <tbody>
            <?php foreach ($peers as $peer): 
                $ruleCount = Database::fetch("SELECT COUNT(*) as cnt FROM cache_peer_access_rules WHERE peer_id = ?", [$peer['id']])['cnt'] ?? 0;
            ?>
            <tr>
                <td><code><?= htmlspecialchars($peer['hostname']) ?></code></td>
                <td><span class="badge badge-<?= $peer['peer_type'] ?>"><?= htmlspecialchars($peer['peer_type']) ?></span></td>
                <td><?= $peer['http_port'] ?></td>
                <td>
                    <?php if ($peer['proxy_only']): ?><span class="badge">proxy-only</span><?php endif; ?>
                    <?php if ($peer['no_query']): ?><span class="badge">no-query</span><?php endif; ?>
                    <?php if ($peer['weight']): ?><span class="badge">weight=<?= $peer['weight'] ?></span><?php endif; ?>
                </td>
                <td><?= htmlspecialchars($peer['login'] ?: '-') ?></td>
                <td>
                    <a href="/peers/access?peer_id=<?= $peer['id'] ?>" class="btn-sm">
                        <?= $ruleCount ?> rule<?= $ruleCount !== 1 ? 's' : '' ?>
                    </a>
                </td>
                <?php if (Auth::isAdmin()): ?>
                <td>
                    <a href="/peers/edit?id=<?= $peer['id'] ?>" class="btn-sm">Edit</a>
                    <form method="POST" action="/peers/delete" style="display:inline" onsubmit="return confirm('Delete peer <?= htmlspecialchars($peer['hostname']) ?>?')">
                        <?= View::csrf() ?>
                        <input type="hidden" name="id" value="<?= $peer['id'] ?>">
                        <button type="submit" class="btn-sm btn-danger">Delete</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="panel" style="margin-top: 16px;">
    <div class="panel-header">
        <h3>Global Routing</h3>
        <a href="/peers/routing" class="btn-sm">Configure</a>
    </div>
    <div class="panel-body">
        <p class="text-muted">Configure <code>never_direct</code>, <code>always_direct</code>, <code>prefer_direct</code> rules with ACL matching.</p>
        <?php 
        $routing = Database::fetchAll("SELECT * FROM routing_rules ORDER BY id");
        if (!empty($routing)): 
        ?>
        <table class="data-table compact" style="margin-top: 12px;">
            <thead><tr><th>Directive</th><th>Action</th><th>ACL</th></tr></thead>
            <tbody>
                <?php foreach ($routing as $r): ?>
                <tr>
                    <td><code><?= htmlspecialchars($r['directive']) ?></code></td>
                    <td><span class="badge badge-<?= $r['action'] === 'allow' ? 'success' : 'danger' ?>"><?= $r['action'] ?></span></td>
                    <td><code><?= htmlspecialchars($r['acl_name']) ?></code></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
