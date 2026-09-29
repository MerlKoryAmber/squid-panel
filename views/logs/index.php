<?php
ob_start();
?>
<form method="GET" action="/logs" id="logFilterForm" class="logs-filter-bar">
    <input class="f-ip" type="text" name="ip" value="<?= htmlspecialchars($filters['ip'] ?? '') ?>" placeholder="Client IP" aria-label="Client IP">
    <input class="f-user" type="text" name="user" value="<?= htmlspecialchars($filters['user'] ?? '') ?>" placeholder="User" aria-label="User">
    <input class="f-status" type="text" name="status" value="<?= htmlspecialchars($filters['status'] ?? '') ?>" placeholder="Status / TCP_*" aria-label="Status">
    <input class="f-url" type="text" name="url" value="<?= htmlspecialchars($filters['url'] ?? '') ?>" placeholder="URL contains" aria-label="URL">
    <label class="logs-inline-label">method
        <select name="method" aria-label="Method">
            <option value="">All</option>
            <option value="GET" <?= ($filters['method'] ?? '') === 'GET' ? 'selected' : '' ?>>GET</option>
            <option value="POST" <?= ($filters['method'] ?? '') === 'POST' ? 'selected' : '' ?>>POST</option>
            <option value="CONNECT" <?= ($filters['method'] ?? '') === 'CONNECT' ? 'selected' : '' ?>>CONNECT</option>
            <option value="HEAD" <?= ($filters['method'] ?? '') === 'HEAD' ? 'selected' : '' ?>>HEAD</option>
        </select>
    </label>
    <label class="logs-inline-label">peer
        <select name="peer" aria-label="Peer">
            <option value="">All</option>
            <option value="DIRECT" <?= ($filters['peer'] ?? '') === 'DIRECT' ? 'selected' : '' ?>>Direct</option>
            <?php foreach ($peers as $peer): ?>
            <option value="<?= htmlspecialchars($peer['name'], ENT_QUOTES) ?>" <?= ($filters['peer'] ?? '') === $peer['name'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($peer['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
    <a href="/logs" class="btn btn-secondary btn-sm">Reset</a>
    <a href="/logs/live" class="btn btn-primary btn-sm">▶ Live</a>
</form>
<?php $pageToolbar = ob_get_clean(); ?>

<div class="card table-card logs-table-card">
    <div class="card-header">
        <h3>Access log</h3>
        <span class="subtitle"><?= count($logs) ?> shown · newest first · last 16&nbsp;MB</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($logs)): ?>
        <div class="empty-state">
            <h4>No log entries found</h4>
            <p>Check filters or verify that Squid is logging to <?= htmlspecialchars(SQUID_ACCESS_LOG) ?></p>
        </div>
        <?php else: ?>
        <div class="table-scroll">
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
