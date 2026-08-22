<div class="page-header">
    <h2>Settings</h2>
</div>

<?php if (!empty($flashError)): ?>
<div class="alert alert-danger"><?= htmlspecialchars((string)$flashError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
<?php endif; ?>
<?php if (!empty($flashSuccess)): ?>
<div class="alert alert-success"><?= htmlspecialchars((string)$flashSuccess, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Squid listen</h3></div>
    <div class="card-body">
        <p style="color:var(--ir-text-muted); font-size:0.82rem;">
            Imported from live conf. Save rewrites <code>/etc/squid/squid.conf</code> after <code>squid -k parse</code>.
        </p>
        <form method="POST" action="/settings/squid">
            <?= View::csrf() ?>
            <div class="form-group">
                <label>http_port (one line per listen)</label>
                <textarea name="http_port" rows="3" placeholder="3128"><?= htmlspecialchars((string)($globals['http_port'] ?? '3128'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
            </div>
            <div class="form-group">
                <label>visible_hostname</label>
                <input type="text" name="visible_hostname" value="<?= htmlspecialchars((string)($globals['visible_hostname'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" placeholder="proxy.example.com">
            </div>
            <div class="form-group">
                <label>coredump_dir</label>
                <input type="text" name="coredump_dir" value="<?= htmlspecialchars((string)($globals['coredump_dir'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" placeholder="/var/spool/squid">
            </div>
            <div class="form-group">
                <label>request_header_access (one line per rule)</label>
                <p style="color:var(--ir-text-muted); font-size:0.82rem;">
                    Testhost: <code>X-Forwarded-For deny all</code> — Squid не шлёт этот заголовок на origin и parent.
                    Без него каскад часто ломается (upstream видит IP клиента).
                </p>
                <textarea name="request_header_access" rows="3" placeholder="X-Forwarded-For deny all"><?= htmlspecialchars((string)($globals['request_header_access'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save and apply to Squid</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Policy → Squid</h3></div>
    <div class="card-body">
        <p style="color:var(--ir-text-muted); font-size:0.82rem;">
            Save on Access / Lists / Cascade / Listen writes <code>spm.db</code> then live <code>/etc/squid/squid.conf</code>
            (backup <code>*.spm-policy-*</code>) after parse. Unmanaged lines (<code>cache</code>, <code>cache_mem</code>) stay in extra.
            This button is the same pipeline if a Save was skipped.
        </p>
        <form method="POST" action="/settings/apply-policy" data-confirm="Rewrite live squid.conf from the panel database? Parse failure leaves live conf unchanged.">
            <?= View::csrf() ?>
            <input type="hidden" name="return" value="/settings">
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Apply to Squid</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Panel IP allowlist (nginx)</h3></div>
    <div class="card-body">
        <p style="color:var(--ir-text-muted); font-size:0.82rem;">
            Empty = no filter. Non-empty = only these IPs/CIDRs + this request’s IP if missing. Always <code>127.0.0.1</code> / <code>::1</code> in nginx file. Wrong list can lock the UI; keep a console.
        </p>
        <p style="font-size:0.82rem;">Your IP now: <code><?= htmlspecialchars((string)($clientIp ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></code></p>
        <form method="POST" action="/settings/allow">
            <?= View::csrf() ?>
            <div class="form-group">
                <label>Allowed IPs (one per line)</label>
                <textarea name="panel_allow_ips" rows="6" placeholder="10.0.0.0/8"><?= htmlspecialchars((string)($settings['panel_allow_ips'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save and reload nginx</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>General</h3></div>
    <div class="card-body">
        <form method="POST" action="/settings/save">
            <?= View::csrf() ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Language</label>
                    <select name="language">
                        <option value="ru" <?= ($settings['language'] ?? 'ru') === 'ru' ? 'selected' : '' ?>>Русский</option>
                        <option value="en" <?= ($settings['language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Theme</label>
                    <select name="theme">
                        <option value="light" <?= ($settings['theme'] ?? 'light') === 'light' ? 'selected' : '' ?>>Light</option>
                        <option value="dark" <?= ($settings['theme'] ?? '') === 'dark' ? 'selected' : '' ?>>Dark</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Change password</h3></div>
    <div class="card-body">
        <?php if (!empty($_SESSION['flash_error'])): ?>
        <p><?= htmlspecialchars($_SESSION['flash_error']) ?></p>
        <?php unset($_SESSION['flash_error']); endif; ?>
        <?php if (!empty($_SESSION['flash_success'])): ?>
        <p><?= htmlspecialchars($_SESSION['flash_success']) ?></p>
        <?php unset($_SESSION['flash_success']); endif; ?>
        <form method="POST" action="/users/password">
            <?= View::csrf() ?>
            <input type="hidden" name="id" value="<?= (int)(Auth::user()['id'] ?? 0) ?>">
            <input type="hidden" name="redirect" value="/settings">
            <div class="form-group">
                <label>Current password</label>
                <input type="password" name="current_password" required autocomplete="current-password">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>New password</label>
                    <input type="password" name="password" required minlength="8" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label>Confirm new password</label>
                    <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update password</button>
            </div>
        </form>
    </div>
</div>
