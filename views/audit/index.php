<?php
$logs = is_array($logs ?? null) ? $logs : [];
?>

<div class="card table-card">
    <div class="card-header">
        <h3>Events</h3>
        <span class="subtitle"><?= count($logs) ?> recorded</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($logs)): ?>
        <div class="empty-state"><h4>No audit events</h4></div>
        <?php else: ?>
        <div class="table-scroll">
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
                <?php foreach ($logs as $event): ?>
                <tr>
                    <td style="font-size:0.78rem; color:var(--ir-text-muted); white-space:nowrap;"><?= htmlspecialchars($event['created_at'] ?? '') ?></td>
                    <td><strong><?= htmlspecialchars($event['user'] ?? 'system') ?></strong></td>
                    <td><span class="badge badge-info"><?= htmlspecialchars($event['action'] ?? '') ?></span></td>
                    <td style="color:var(--ir-text-secondary); font-size:0.85rem;"><?= htmlspecialchars($event['details'] ?? '') ?></td>
                    <td><code class="code-inline"><?= htmlspecialchars($event['ip_address'] ?? '') ?></code></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
