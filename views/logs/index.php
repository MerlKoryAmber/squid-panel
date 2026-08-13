<div class="page-header">
    <h2>Access Logs</h2>
    <form method="GET" action="/logs" class="form-row" style="gap: 8px;">
        <input type="text" name="ip" placeholder="IP" value="<?= htmlspecialchars($filters['ip'] ?? '') ?>">
        <input type="text" name="user" placeholder="User" value="<?= htmlspecialchars($filters['user'] ?? '') ?>">
        <input type="text" name="status" placeholder="Status" value="<?= htmlspecialchars($filters['status'] ?? '') ?>">
        <input type="text" name="url" placeholder="URL" value="<?= htmlspecialchars($filters['url'] ?? '') ?>">
        <select name="method">
            <option value="">Method</option>
            <option value="GET" <?= ($filters['method'] ?? '') === 'GET' ? 'selected' : '' ?>>GET</option>
            <option value="POST" <?= ($filters['method'] ?? '') === 'POST' ? 'selected' : '' ?>>POST</option>
            <option value="CONNECT" <?= ($filters['method'] ?? '') === 'CONNECT' ? 'selected' : '' ?>>CONNECT</option>
        </select>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="/logs" class="btn">Reset</a>
    </form>
</div>

<div class="panel">
    <table class="data-table compact">
        <thead>
            <tr><th>Time</th><th>Client</th><th>User</th><th>URL</th><th>Route</th><th>Status</th><th>Size</th></tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <?php $h = LogParser::hierarchyLabel($log['hierarchy'] ?? ''); ?>
            <tr>
                <td><?= htmlspecialchars($log['timestamp']) ?></td>
                <td><?= htmlspecialchars($log['client_ip']) ?></td>
                <td><?= htmlspecialchars($log['user'] ?: '-') ?></td>
                <td class="truncate"><?= htmlspecialchars($log['url']) ?></td>
                <td>
                    <span class="badge <?= $h['class'] ?>"><?= htmlspecialchars($h['label']) ?></span>
                    <?php if (!empty($log['peer_host'])): ?>
                    <small style="color:var(--interros-text-muted)">via <?= htmlspecialchars($log['peer_host']) ?></small>
                    <?php endif; ?>
                </td>
                <td><span class="badge <?= strpos($log['status'], 'TCP_MISS') !== false ? 'badge-warning' : 'badge-success' ?>"><?= htmlspecialchars(explode('/', $log['status'])[0]) ?></span></td>
                <td><?= number_format($log['bytes']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
            <tr><td colspan="7" class="log-empty">No logs found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="?page=<?= $i ?>&ip=<?= urlencode($filters['ip'] ?? '') ?>&user=<?= urlencode($filters['user'] ?? '') ?>&status=<?= urlencode($filters['status'] ?? '') ?>&url=<?= urlencode($filters['url'] ?? '') ?>&method=<?= urlencode($filters['method'] ?? '') ?>" class="<?= $i === $currentPage ? 'current' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
