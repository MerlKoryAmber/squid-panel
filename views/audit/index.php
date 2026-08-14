<div class="page-header">
    <h2>Audit Log</h2>
</div>

<div class="card">
    <div class="card-header">
        <h3>Events</h3>
        <span class="subtitle"><?= count($events) ?> recorded</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($events)): ?>
        <div class="empty-state"><h4>No audit events</h4></div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                <tr>
                    <td style="font-size:0.78rem; color:var(--ir-text-muted); white-space:nowrap;"><?= $event['created_at'] ?></td>
                    <td><strong><?= htmlspecialchars($event['user'] ?? 'system') ?></strong></td>
                    <td><span class="badge badge-info"><?= htmlspecialchars($event['action']) ?></span></td>
                    <td style="color:var(--ir-text-secondary); font-size:0.85rem;"><?= htmlspecialchars($event['details'] ?? '') ?></td>
                    <td><code class="code-inline"><?= htmlspecialchars($event['ip'] ?? '') ?></code></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
