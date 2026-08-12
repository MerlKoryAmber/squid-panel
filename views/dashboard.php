<div class="dashboard">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Service Status</div>
            <div class="stat-value" id="statusValue"><?= ucfirst($status['status'] ?? 'unknown') ?></div>
            <div class="stat-meta">Squid <?= htmlspecialchars($version) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active Connections</div>
            <div class="stat-value"><?= number_format($connections) ?></div>
            <div class="stat-meta">Current</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">ACL Rules</div>
            <div class="stat-value"><?= count($acls ?? []) ?></div>
            <div class="stat-meta">Defined</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Cache Peers</div>
            <div class="stat-value"><?= count($peers ?? []) ?></div>
            <div class="stat-meta">Configured</div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="panel">
            <div class="panel-header">
                <h3>Quick Actions</h3>
            </div>
            <div class="panel-body">
                <div class="action-buttons">
                    <?php if (Auth::isAdmin()): ?>
                    <button class="btn" onclick="serviceAction('start')">Start</button>
                    <button class="btn" onclick="serviceAction('stop')">Stop</button>
                    <button class="btn" onclick="serviceAction('restart')">Restart</button>
                    <button class="btn btn-primary" onclick="serviceAction('reconfigure')">Reconfigure</button>
                    <?php else: ?>
                    <p class="text-muted">Operator view — no service controls</p>
                    <?php endif; ?>
                </div>
                <div id="serviceMessage" class="message-box" style="display:none;"></div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h3>Recent Audit</h3>
                <a href="/audit" class="link">View all</a>
            </div>
            <div class="panel-body">
                <table class="data-table compact">
                    <thead>
                        <tr><th>Time</th><th>User</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($auditLogs, 0, 5) as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['created_at']) ?></td>
                            <td><?= htmlspecialchars($log['user']) ?></td>
                            <td><?= htmlspecialchars($log['action']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h3>Recent Access Logs</h3>
            <a href="/logs" class="link">View all</a>
        </div>
        <div class="panel-body">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Client</th>
                        <th>User</th>
                        <th>Status</th>
                        <th>Method</th>
                        <th>URL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentLogs as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['timestamp'] ?? '') ?></td>
                        <td><?= htmlspecialchars($log['client_ip'] ?? '') ?></td>
                        <td><?= htmlspecialchars($log['user'] ?: '-') ?></td>
                        <td><span class="badge <?= strpos($log['status'] ?? '', 'TCP_MISS') !== false ? 'badge-warning' : 'badge-success' ?>"><?= htmlspecialchars(explode('/', $log['status'] ?? '')[0]) ?></span></td>
                        <td><?= htmlspecialchars($log['method'] ?? '') ?></td>
                        <td class="truncate" title="<?= htmlspecialchars($log['url'] ?? '') ?>"><?= htmlspecialchars($log['url'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($stats['domains'])): ?>
    <div class="dashboard-grid">
        <div class="panel">
            <div class="panel-header"><h3>Top Domains (24h)</h3></div>
            <div class="panel-body">
                <?php foreach (array_slice($stats['domains'], 0, 5, true) as $domain => $count): ?>
                <div class="bar-item">
                    <span class="bar-label"><?= htmlspecialchars($domain) ?></span>
                    <span class="bar-value"><?= $count ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="panel">
            <div class="panel-header"><h3>Top Users by Traffic (24h)</h3></div>
            <div class="panel-body">
                <?php foreach (array_slice($stats['users'], 0, 5, true) as $user => $bytes): ?>
                <div class="bar-item">
                    <span class="bar-label"><?= htmlspecialchars($user) ?></span>
                    <span class="bar-value"><?= number_format($bytes / 1024 / 1024, 1) ?> MB</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
