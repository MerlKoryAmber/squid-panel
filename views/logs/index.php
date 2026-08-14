<div class="page-header">
    <h2>Access Logs</h2>
    <a href="/logs/live" class="btn btn-primary">▶ Live View</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>Access Log Entries</h3>
        <span class="subtitle"><?= count($logs) ?> entries shown</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($logs)): ?>
        <div class="empty-state">
            <h4>No log entries found</h4>
            <p>Check filters or verify that Squid is logging to <?= htmlspecialchars(SQUID_ACCESS_LOG) ?></p>
        </div>
        <?php else: ?>
        <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Client</th>
                    <th>Method</th>
                    <th>URL</th>
                    <th>Status</th>
                    <th>Size</th>
                    <th>User</th>
                    <th>Peer</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $entry): ?>
                <tr>
                    <td style="font-size:0.78rem; color:var(--ir-text-muted); white-space:nowrap;"><?= htmlspecialchars($entry['timestamp'] ?? '') ?></td>
                    <td><code class="code-inline"><?= htmlspecialchars($entry['client_ip'] ?? '') ?></code></td>
                    <td><span class="badge badge-default"><?= htmlspecialchars($entry['method'] ?? '') ?></span></td>
                    <td style="max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($entry['url'] ?? '') ?>"><?= htmlspecialchars($entry['url'] ?? '') ?></td>
                    <td>
                        <?php $statusCode = (int)($entry['status'] ?? 0); ?>
                        <span class="badge badge-<?= $statusCode >= 400 ? 'danger' : ($statusCode >= 300 ? 'warning' : 'success') ?>">
                            <?= htmlspecialchars($entry['status'] ?? '') ?>
                        </span>
                    </td>
                    <td style="text-align:right; font-size:0.82rem; color:var(--ir-text-secondary); white-space:nowrap;"><?= number_format((int)($entry['bytes'] ?? 0)) ?></td>
                    <td style="font-size:0.82rem;"><?= htmlspecialchars($entry['user'] ?: '-') ?></td>
                    <td style="font-size:0.78rem; color:var(--ir-text-muted);"><?= htmlspecialchars($entry['hierarchy'] ?? '') ?>/<?= htmlspecialchars($entry['peer_host'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
