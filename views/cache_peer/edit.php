<div class="page-header">
    <h2><?= $peer ? 'Edit Peer' : 'Add Peer' ?></h2>
</div>

<div class="panel">
    <form method="POST" action="<?= $peer ? '/peers/update' : '/peers/store' ?>">
        <?= View::csrf() ?>
        <?php if ($peer): ?><input type="hidden" name="id" value="<?= $peer['id'] ?>"><?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label>Hostname / IP</label>
                <input type="text" name="hostname" value="<?= htmlspecialchars($peer['hostname'] ?? '') ?>" required placeholder="proxy2.example.com">
            </div>
            <div class="form-group">
                <label>Type</label>
                <select name="peer_type" required>
                    <?php foreach ($types as $key => $label): ?>
                    <option value="<?= $key ?>" <?= ($peer['peer_type'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>HTTP Port</label>
                <input type="number" name="http_port" value="<?= htmlspecialchars($peer['http_port'] ?? '3128') ?>" required>
            </div>
            <div class="form-group">
                <label>ICP Port (optional)</label>
                <input type="number" name="icp_port" value="<?= htmlspecialchars($peer['icp_port'] ?? '0') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Weight</label>
                <input type="number" name="weight" value="<?= htmlspecialchars($peer['weight'] ?? '0') ?>" min="0" placeholder="0">
                <div class="help-text">Higher weight = preferred peer for load balancing</div>
            </div>
            <div class="form-group">
                <label>Connect Timeout (seconds)</label>
                <input type="number" name="connect_timeout" value="<?= htmlspecialchars($peer['connect_timeout'] ?? '0') ?>" min="0">
            </div>
        </div>

        <div class="form-group">
            <label>Login</label>
            <input type="text" name="login" value="<?= htmlspecialchars($peer['login'] ?? '') ?>" placeholder="PASS, *:password, or NEGOTIATE">
            <div class="help-text">
                <code>PASS</code> — pass client credentials | 
                <code>*:password</code> — fixed password | 
                <code>NEGOTIATE</code> — Kerberos/NTLM forwarding
            </div>
        </div>

        <div class="form-group">
            <label>Options</label>
            <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                <label style="display: flex; align-items: center; gap: 6px; font-weight: 400; cursor: pointer;">
                    <input type="checkbox" name="proxy_only" <?= ($peer['proxy_only'] ?? 0) ? 'checked' : '' ?>> proxy-only
                </label>
                <label style="display: flex; align-items: center; gap: 6px; font-weight: 400; cursor: pointer;">
                    <input type="checkbox" name="no_query" <?= ($peer['no_query'] ?? 0) ? 'checked' : '' ?>> no-query
                </label>
                <label style="display: flex; align-items: center; gap: 6px; font-weight: 400; cursor: pointer;">
                    <input type="checkbox" name="no_digest" <?= ($peer['no_digest'] ?? 0) ? 'checked' : '' ?>> no-digest
                </label>
            </div>
        </div>

        <div class="form-group">
            <label>Additional Options</label>
            <input type="text" name="options" value="<?= htmlspecialchars($peer['options'] ?? '') ?>" placeholder="allow-miss, no-tproxy, ...">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Peer</button>
            <a href="/peers" class="btn">Cancel</a>
        </div>
    </form>
</div>
