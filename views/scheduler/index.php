<div class="page-header">
    <h2>Scheduler</h2>
    <?php if (Auth::isAdmin()): ?>
    <button class="btn btn-primary" onclick="document.getElementById('createJobForm').style.display='block'">+ Add Job</button>
    <?php endif; ?>
</div>

<div id="createJobForm" class="panel" style="display:none; margin-bottom: 16px;">
    <div class="panel-header"><h3>New Scheduled Job</h3></div>
    <div class="panel-body">
        <form method="POST" action="/scheduler/store">
            <?= View::csrf() ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" required>
                        <option value="log_rotate">Log Rotate</option>
                        <option value="config_reload">Config Reload</option>
                        <option value="backup">Backup</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Schedule (cron expression)</label>
                <input type="text" name="schedule" placeholder="0 0 * * *" required>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="enabled" checked> Enabled</label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" class="btn" onclick="document.getElementById('createJobForm').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="panel">
    <table class="data-table">
        <thead>
            <tr><th>Name</th><th>Type</th><th>Schedule</th><th>Status</th><th>Last Run</th><?php if (Auth::isAdmin()): ?><th>Actions</th><?php endif; ?></tr>
        </thead>
        <tbody>
            <?php foreach ($jobs as $job): ?>
            <tr>
                <td><?= htmlspecialchars($job['name']) ?></td>
                <td><span class="badge"><?= htmlspecialchars($job['type']) ?></span></td>
                <td><code><?= htmlspecialchars($job['schedule']) ?></code></td>
                <td><span class="badge badge-<?= $job['enabled'] ? 'success' : 'danger' ?>"><?= $job['enabled'] ? 'Enabled' : 'Disabled' ?></span></td>
                <td><?= htmlspecialchars($job['last_run'] ?? 'Never') ?></td>
                <?php if (Auth::isAdmin()): ?>
                <td>
                    <form method="POST" action="/scheduler/toggle" style="display:inline">
                        <?= View::csrf() ?>
                        <input type="hidden" name="id" value="<?= $job['id'] ?>">
                        <button type="submit" class="btn-sm"><?= $job['enabled'] ? 'Disable' : 'Enable' ?></button>
                    </form>
                    <form method="POST" action="/scheduler/delete" style="display:inline" onsubmit="return confirm('Delete job?')">
                        <?= View::csrf() ?>
                        <input type="hidden" name="id" value="<?= $job['id'] ?>">
                        <button type="submit" class="btn-sm btn-danger">Delete</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($jobs)): ?>
            <tr><td colspan="6" class="log-empty">No scheduled jobs</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
