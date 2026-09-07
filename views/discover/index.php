<?php
$h = function ($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$domains = is_array($domains ?? null) ? $domains : [];
?>
<div class="page-header">
    <h2>Domain discover</h2>
</div>

<?php if (!empty($flashError)): ?>
<div class="alert alert-danger"><?= $h($flashError) ?></div>
<?php endif; ?>
<?php if (!empty($flashSuccess)): ?>
<div class="alert alert-success"><?= $h($flashSuccess) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>How it works</h3></div>
    <div class="card-body" style="font-size:0.9rem; color:var(--ir-text-secondary);">
        <p>Opens the URL in a headless browser on this host (via spmd), records <strong>request</strong> hostnames from NetLog (not browser telemetry), then keeps only <strong>second-level domains</strong> (e.g. <code>cdn.example.com</code> → <code>example.com</code>). <strong>Not Squid</strong> — no access.log, no squid.conf.</p>
        <p>Optional filter hides common ad/analytics domains from a short built-in denylist (<?= (int)($denylistCount ?? 0) ?> entries). Extend in <code>app/Data/discover_ad_denylist.txt</code>.</p>
        <p>SSRF fail-closed: only http/https to public IPs. One job at a time, ~45s timeout. Needs Chromium/Chrome installed on the server.</p>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Discover</h3></div>
    <div class="card-body">
        <?php if (empty($isAdmin)): ?>
        <p style="color:var(--ir-text-muted);">Admin only.</p>
        <?php else: ?>
        <form method="POST" action="/discover/run">
            <?= View::csrf() ?>
            <div class="form-group">
                <label>Site URL</label>
                <input type="text" name="url" value="<?= $h($lastUrl ?? '') ?>" placeholder="https://example.com/" required>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="hide_ads" value="1" <?= !empty($hideAds) ? 'checked' : '' ?>>
                    Hide common ads / analytics
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Run discover</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($domains) || !empty($removedAds)): ?>
<div class="card">
    <div class="card-header"><h3>Domains (<?= count($domains) ?>)</h3></div>
    <div class="card-body">
        <textarea id="discover-hosts" rows="<?= min(20, max(6, count($domains))) ?>" readonly><?= $h(implode("\n", $domains)) ?></textarea>
        <div class="form-actions" style="margin-top:10px;">
            <button type="button" class="btn btn-secondary" id="discover-copy">Copy list</button>
        </div>
        <script>
        (function () {
            var btn = document.getElementById('discover-copy');
            var ta = document.getElementById('discover-hosts');
            if (!btn || !ta) return;
            btn.addEventListener('click', function () {
                ta.select();
                try {
                    document.execCommand('copy');
                    btn.textContent = 'Copied';
                } catch (e) {
                    btn.textContent = 'Select and Ctrl+C';
                }
            });
        })();
        </script>
    </div>
</div>
<?php endif; ?>
