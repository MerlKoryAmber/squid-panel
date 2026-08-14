<div class="page-header">
    <h2>Backup & Restore</h2>
</div>

<div class="card">
    <div class="card-header"><h3>Create Backup</h3></div>
    <div class="card-body">
        <p style="color:var(--ir-text-secondary); margin-bottom:var(--space-md);">Download a complete backup of your Squid configuration and SPM database.</p>
        <a href="/backup/create" class="btn btn-primary">📦 Create Backup</a>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Restore</h3></div>
    <div class="card-body">
        <form method="POST" action="/backup/restore" enctype="multipart/form-data">
            <?= View::csrf() ?>
            <div class="form-group">
                <label>Backup File</label>
                <input type="file" name="backup_file" accept=".zip,.sql,.conf" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">🔄 Restore</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Existing Backups</h3></div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($backups)): ?>
        <div class="empty-state"><h4>No backups found</h4></div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr><th>File</th><th>Size</th><th>Created</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($backups as $b): ?>
                <tr>
                    <td><code class="code-inline"><?= htmlspecialchars($b['name']) ?></code></td>
                    <td><?= $b['size'] ?></td>
                    <td style="color:var(--ir-text-muted); font-size:0.82rem;"><?= $b['date'] ?></td>
                    <td>
                        <a href="/backup/download?file=<?= urlencode($b['name']) ?>" class="btn btn-sm btn-secondary">Download</a>
                        <form method="POST" action="/backup/delete" style="display:inline" onsubmit="return confirm('Delete backup?')">
                            <?= View::csrf() ?>
                            <input type="hidden" name="file" value="<?= htmlspecialchars($b['name']) ?>">
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
