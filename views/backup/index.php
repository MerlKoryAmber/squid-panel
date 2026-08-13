<div class="page-header">
    <h2>Backups</h2>
    <?php if (Auth::isAdmin()): ?>
    <form method="POST" action="/backup/create" style="display:inline">
        <?= View::csrf() ?>
        <button type="submit" class="btn btn-primary">+ Create Backup</button>
    </form>
    <?php endif; ?>
</div>

<div class="panel">
    <table class="data-table">
        <thead>
            <tr><th>Name</th><th>Size</th><th>Date</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($backups as $backup): ?>
            <tr>
                <td><?= htmlspecialchars($backup['name']) ?></td>
                <td><?= number_format($backup['size'] / 1024, 1) ?> KB</td>
                <td><?= htmlspecialchars($backup['date']) ?></td>
                <td>
                    <a href="/backup/download?name=<?= urlencode($backup['name']) ?>" class="btn-sm">Download</a>
                    <?php if (Auth::isAdmin()): ?>
                    <form method="POST" action="/backup/restore" style="display:inline" onsubmit="return confirm('Restore from this backup? Current config will be overwritten.')">
                        <?= View::csrf() ?>
                        <input type="hidden" name="name" value="<?= htmlspecialchars($backup['name']) ?>">
                        <button type="submit" class="btn-sm">Restore</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($backups)): ?>
            <tr><td colspan="4" class="log-empty">No backups yet</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
