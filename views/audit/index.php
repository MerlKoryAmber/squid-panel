<div class="page-header"><h2>Audit Log</h2></div>

<div class="panel" style="margin-bottom: 16px;">
    <div class="panel-body">
        <form method="GET" action="/audit" class="form-row">
            <div class="form-group">
                <label>User</label>
                <input type="text" name="user" value="<?= htmlspecialchars($filters['user'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Action</label>
                <input type="text" name="action" value="<?= htmlspecialchars($filters['action'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
            </div>
            <div class="form-actions" style="align-self: flex-end;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="/audit" class="btn">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="panel">
    <table class="data-table">
        <thead>
            <tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= htmlspecialchars($log['created_at'] ?? '') ?></td>
                <td><?= htmlspecialchars($log['user'] ?? '') ?></td>
                <td><span class="badge"><?= htmlspecialchars($log['action'] ?? '') ?></span></td>
                <td><?= htmlspecialchars($log['details'] ?? '') ?></td>
                <td><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
            <tr><td colspan="5" class="log-empty">No audit records</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
