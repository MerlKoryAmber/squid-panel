<div class="page-header">
    <h2>Logs</h2>
    <a href="/logs/live" class="btn btn-primary">▶ Live View</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>Access Log</h3>
        <span class="subtitle"><?= count($entries) ?> entries</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($entries)): ?>
        <div class="empty-state"><h4>No log entries</h4></div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Client</th>
                    <th>Method</th>
                    <th>URL</th>
                    <th>Status</th>
                    <th>Size</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                <tr>
                    <td style="font-size:0.78rem; color:var(--ir-text-muted); white-space:nowrap;"><?= $entry['time'] ?></td>
                    <td><code class="code-inline"><?= htmlspecialchars($entry['client']) ?></code></td>
                    <td><?= $entry['method'] ?></td>
                    <td style="max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($entry['url']) ?></td>
                    <td>
                        <span class="badge badge-<?= $entry['status'] >= 400 ? 'danger' : ($entry['status'] >= 300 ? 'warning' : 'success') ?>">
                            <?= $entry['status'] ?>
                        </span>
                    </td>
                    <td style="text-align:right; font-size:0.82rem; color:var(--ir-text-secondary);"><?= $entry['size'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
