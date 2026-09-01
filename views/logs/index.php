<div class="page-header">
    <h2>Access Logs</h2>
    <a href="/logs/live" class="btn btn-primary">▶ Live View</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>Filters</h3>
        <span class="subtitle">Narrow down log entries</span>
    </div>
    <div class="card-body">
        <form method="GET" action="/logs" id="logFilterForm">
            <div class="form-row">
                <div class="form-group">
                    <label>Client IP</label>
                    <input type="text" name="ip" value="<?= htmlspecialchars($filters['ip'] ?? '') ?>" placeholder="192.168.1.1">
                </div>
                <div class="form-group">
                    <label>User</label>
                    <input type="text" name="user" value="<?= htmlspecialchars($filters['user'] ?? '') ?>" placeholder="username">
                </div>
                <div class="form-group">
                    <label>Status Code</label>
                    <input type="text" name="status" value="<?= htmlspecialchars($filters['status'] ?? '') ?>" placeholder="200, 404, TCP_MISS">
                </div>
                <div class="form-group" style="flex: 2;">
                    <label>URL Contains</label>
                    <input type="text" name="url" value="<?= htmlspecialchars($filters['url'] ?? '') ?>" placeholder="example.com">
                </div>
                <div class="form-group">
                    <label>Method</label>
                    <select name="method">
                        <option value="">All</option>
                        <option value="GET" <?= ($filters['method'] ?? '') === 'GET' ? 'selected' : '' ?>>GET</option>
                        <option value="POST" <?= ($filters['method'] ?? '') === 'POST' ? 'selected' : '' ?>>POST</option>
                        <option value="CONNECT" <?= ($filters['method'] ?? '') === 'CONNECT' ? 'selected' : '' ?>>CONNECT</option>
                        <option value="HEAD" <?= ($filters['method'] ?? '') === 'HEAD' ? 'selected' : '' ?>>HEAD</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Peer</label>
                    <select name="peer">
                        <option value="">All</option>
                        <option value="DIRECT" <?= ($filters['peer'] ?? '') === 'DIRECT' ? 'selected' : '' ?>>Direct (no peer)</option>
                        <?php foreach ($peers as $peer): ?>
                        <option value="<?= htmlspecialchars($peer['name'], ENT_QUOTES) ?>" <?= ($filters['peer'] ?? '') === $peer['name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($peer['name']) ?><?= !empty($peer['hostname']) ? ' (' . htmlspecialchars($peer['hostname']) . ')' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="/logs" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Access Log Entries</h3>
        <span class="subtitle"><?= count($logs) ?> entries shown (newest first)</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($logs)): ?>
        <div class="empty-state">
            <h4>No log entries found</h4>
            <p>Check filters or verify that Squid is logging to <?= htmlspecialchars(SQUID_ACCESS_LOG) ?></p>
        </div>
        <?php else: ?>
        <div style="overflow-x: auto;">
        <table class="data-table js-col-sort" id="logsTable" data-sort-col="timestamp" data-sort-dir="desc">
            <thead>
                <tr>
                    <th class="js-sort" data-col="timestamp" role="button" tabindex="0" aria-sort="descending">Time</th>
                    <th class="js-sort" data-col="client_ip" role="button" tabindex="0" aria-sort="none">Client</th>
                    <th class="js-sort" data-col="method" role="button" tabindex="0" aria-sort="none">Method</th>
                    <th class="js-sort" data-col="url" role="button" tabindex="0" aria-sort="none">URL</th>
                    <th class="js-sort" data-col="status" role="button" tabindex="0" aria-sort="none">Status</th>
                    <th class="js-sort" data-col="bytes" role="button" tabindex="0" aria-sort="none">Size</th>
                    <th class="js-sort" data-col="user" role="button" tabindex="0" aria-sort="none">User</th>
                    <th class="js-sort" data-col="peer" role="button" tabindex="0" aria-sort="none">Peer</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $entry): ?>
                <?php
                $statusCode = (int)($entry['status'] ?? 0);
                $peerLabel = trim(($entry['hierarchy'] ?? '') . '/' . ($entry['peer_host'] ?? ''), '/');
                ?>
                <tr>
                    <td data-col="timestamp" data-sort="<?= sprintf('%010d', (int)($entry['timestamp_unix'] ?? 0)) ?>" style="font-size:0.78rem; color:var(--ir-text-muted); white-space:nowrap;"><?= htmlspecialchars($entry['timestamp'] ?? '') ?></td>
                    <td data-col="client_ip" data-sort="<?= htmlspecialchars($entry['client_ip'] ?? '', ENT_QUOTES) ?>"><code class="code-inline"><?= htmlspecialchars($entry['client_ip'] ?? '') ?></code></td>
                    <td data-col="method" data-sort="<?= htmlspecialchars($entry['method'] ?? '', ENT_QUOTES) ?>"><span class="badge badge-default"><?= htmlspecialchars($entry['method'] ?? '') ?></span></td>
                    <td data-col="url" data-sort="<?= htmlspecialchars($entry['url'] ?? '', ENT_QUOTES) ?>" style="max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($entry['url'] ?? '') ?>"><?= htmlspecialchars($entry['url'] ?? '') ?></td>
                    <td data-col="status" data-sort="<?= htmlspecialchars($entry['status'] ?? '', ENT_QUOTES) ?>">
                        <span class="badge badge-<?= $statusCode >= 400 ? 'danger' : ($statusCode >= 300 ? 'warning' : 'success') ?>">
                            <?= htmlspecialchars($entry['status'] ?? '') ?>
                        </span>
                    </td>
                    <td data-col="bytes" data-sort="<?= sprintf('%012d', (int)($entry['bytes'] ?? 0)) ?>" style="text-align:right; font-size:0.82rem; color:var(--ir-text-secondary); white-space:nowrap;"><?= number_format((int)($entry['bytes'] ?? 0)) ?></td>
                    <td data-col="user" data-sort="<?= htmlspecialchars($entry['user'] ?: '-', ENT_QUOTES) ?>" style="font-size:0.82rem;"><?= htmlspecialchars($entry['user'] ?: '-') ?></td>
                    <td data-col="peer" data-sort="<?= htmlspecialchars($peerLabel, ENT_QUOTES) ?>" style="font-size:0.78rem; color:var(--ir-text-muted);"><?= htmlspecialchars($peerLabel) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<script src="<?= htmlspecialchars(View::asset('/assets/js/table-sort.js')) ?>"></script>
