<div class="page-header">
    <h2>Access Logs</h2>
    <a href="/logs/export?<?= http_build_query(array_filter($filters)) ?>" class="btn">Export CSV</a>
</div>

<div class="panel">
    <form method="GET" action="/logs" class="filter-bar">
        <input type="text" name="ip" placeholder="IP address" value="<?= htmlspecialchars($filters['ip']) ?>">
        <input type="text" name="user" placeholder="User" value="<?= htmlspecialchars($filters['user']) ?>">
        <input type="text" name="status" placeholder="Status code" value="<?= htmlspecialchars($filters['status']) ?>">
        <input type="text" name="url" placeholder="URL" value="<?= htmlspecialchars($filters['url']) ?>">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="/logs" class="btn">Reset</a>
    </form>

    <table class="data-table">
        <thead>
            <tr><th>Time</th><th>Client</th><th>User</th><th>Status</th><th>Bytes</th><th>Method</th><th>URL</th></tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= htmlspecialchars($log['timestamp']) ?></td>
                <td><?= htmlspecialchars($log['client_ip']) ?></td>
                <td><?= htmlspecialchars($log['user'] ?: '-') ?></td>
                <td><span class="badge <?= strpos($log['status'], 'TCP_MISS') !== false ? 'badge-warning' : 'badge-success' ?>"><?= htmlspecialchars(explode('/', $log['status'])[0]) ?></span></td>
                <td><?= number_format($log['bytes']) ?></td>
                <td><?= htmlspecialchars($log['method']) ?></td>
                <td class="truncate" title="<?= htmlspecialchars($log['url']) ?>"><?= htmlspecialchars($log['url']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
