<div class="page-header"><h2>Statistics</h2></div>

<div class="dashboard-grid">
    <div class="panel">
        <div class="panel-header"><h3>Top Domains (24h)</h3></div>
        <div class="panel-body">
            <?php if (!empty($stats['domains'])): ?>
            <?php foreach ($stats['domains'] as $domain => $count): ?>
            <div class="bar-item" style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid var(--interros-border);">
                <span><?= htmlspecialchars($domain) ?></span>
                <span class="badge"><?= number_format($count) ?></span>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <p class="log-empty">No data available. Squid access log may be empty or not configured.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="panel">
        <div class="panel-header"><h3>Top Users by Traffic (24h)</h3></div>
        <div class="panel-body">
            <?php if (!empty($stats['users'])): ?>
            <?php foreach ($stats['users'] as $user => $bytes): ?>
            <div class="bar-item" style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid var(--interros-border);">
                <span><?= htmlspecialchars($user) ?></span>
                <span class="badge"><?= number_format($bytes / 1024 / 1024, 1) ?> MB</span>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <p class="log-empty">No user traffic data.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="panel" style="margin-top: 16px;">
    <div class="panel-header"><h3>Response Codes (24h)</h3></div>
    <div class="panel-body">
        <?php if (!empty($stats['codes'])): ?>
        <?php foreach ($stats['codes'] as $code => $count): ?>
        <div class="bar-item" style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid var(--interros-border);">
            <span><code><?= htmlspecialchars($code) ?></code></span>
            <span class="badge"><?= number_format($count) ?></span>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <p class="log-empty">No response code data.</p>
        <?php endif; ?>
    </div>
</div>
