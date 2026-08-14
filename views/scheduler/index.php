<div class="page-header">
    <h2>Task Scheduler</h2>
    <a href="/scheduler/create" class="btn btn-primary">+ Add Task</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>Scheduled Tasks</h3>
        <span class="subtitle">Cron-based automation</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($tasks)): ?>
        <div class="empty-state">
            <h4>No tasks scheduled</h4>
            <p>Automate config reloads, log rotation, and more.</p>
        </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Schedule</th>
                    <th>Command</th>
                    <th>Status</th>
                    <th style="width:140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $task): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($task['name']) ?></strong></td>
                    <td><code class="code-inline"><?= htmlspecialchars($task['schedule']) ?></code></td>
                    <td style="font-size:0.82rem; color:var(--ir-text-secondary);"><?= htmlspecialchars($task['command']) ?></td>
                    <td>
                        <span class="badge badge-<?= ($task['enabled'] ?? 1) ? 'success' : 'default' ?>">
                            <?= ($task['enabled'] ?? 1) ? 'Active' : 'Disabled' ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" action="/scheduler/toggle" style="display:inline">
                            <?= View::csrf() ?>
                            <input type="hidden" name="id" value="<?= $task['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-secondary"><?= ($task['enabled'] ?? 1) ? 'Disable' : 'Enable' ?></button>
                        </form>
                        <form method="POST" action="/scheduler/delete" style="display:inline" onsubmit="return confirm('Delete task?')">
                            <?= View::csrf() ?>
                            <input type="hidden" name="id" value="<?= $task['id'] ?>">
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
