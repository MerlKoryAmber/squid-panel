<div class="page-header">
    <h2>Instructions</h2>
</div>

<div class="card">
    <div class="card-header">
        <h3>Guides</h3>
        <span class="subtitle"><?= count($guides) ?></span>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="data-table">
            <tbody>
                <?php foreach ($guides as $slug => $guide): ?>
                <tr>
                    <td>
                        <a href="/instructions?g=<?= htmlspecialchars($slug, ENT_QUOTES) ?>"><?= htmlspecialchars($guide['title']) ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
