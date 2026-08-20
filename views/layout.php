<!DOCTYPE html>
<html lang="<?= DEFAULT_LANG ?? 'ru' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Squid Proxy Manager') ?> — SPM</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(View::asset('/assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(View::asset('/assets/css/inter-font.css')) ?>">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <style>
        .brand-mark{box-sizing:border-box;flex:0 0 48px;width:48px;height:30px;overflow:hidden;display:flex;align-items:center;justify-content:center;background:#fff;border:1px solid #c9a96e;border-radius:6px}
        .brand-mark img{display:block;max-width:42px;max-height:24px;width:auto;height:auto}
        .brand-mark-lg{flex:0 0 88px;width:88px;height:52px;margin:0 auto 14px;border-radius:8px}
        .brand-mark-lg img{max-width:78px;max-height:44px}
    </style>
</head>
<body>
    <div class="app-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-mark" aria-hidden="true">
                    <img src="<?= htmlspecialchars(View::asset('/assets/img/spm-mascot.png')) ?>" alt="" width="42" height="24">
                </div>
                <div class="brand-copy">
                    <h1>Squid Proxy Manager</h1>
                    <span>Network Security</span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="/dashboard" class="<?= ($active ?? '') === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a></li>
                    <li><a href="/acl" class="<?= ($active ?? '') === 'acl' ? 'active' : '' ?>">🛡 <?= PolicyUi::isSimple() ? 'Lists' : 'ACLs' ?></a></li>
                    <li><a href="/http_access" class="<?= ($active ?? '') === 'http_access' ? 'active' : '' ?>">🔒 <?= PolicyUi::isSimple() ? 'HTTP rules' : 'HTTP Access' ?></a></li>
                    <li><a href="/peers" class="<?= ($active ?? '') === 'peers' ? 'active' : '' ?>">🔗 Cascade</a></li>
                    <li><a href="/auth" class="<?= ($active ?? '') === 'auth' ? 'active' : '' ?>">🔑 Authentication</a></li>
                    <li><a href="/users" class="<?= ($active ?? '') === 'users' ? 'active' : '' ?>">👤 Users</a></li>
                    <li><a href="/logs" class="<?= ($active ?? '') === 'logs' ? 'active' : '' ?>">📋 Logs</a></li>
                    <li><a href="/stats" class="<?= ($active ?? '') === 'stats' ? 'active' : '' ?>">📈 Statistics</a></li>
                    <li><a href="/audit" class="<?= ($active ?? '') === 'audit' ? 'active' : '' ?>">📜 Audit</a></li>
                    <?php if (Auth::isAdmin()): ?>
                    <li><a href="/settings" class="<?= ($active ?? '') === 'settings' ? 'active' : '' ?>">⚙ Settings</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <?php if (Auth::check()): ?>
                <div style="margin-bottom: 8px;">
                    <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Guest') ?></strong>
                    <span class="role" style="margin-left: 6px;"><?= htmlspecialchars($_SESSION['role'] ?? '') ?></span>
                </div>
                <a href="/logout">Logout →</a>
                <?php endif; ?>
                <div style="margin-top: 8px; opacity: 0.6;">v<?= SPM_VERSION ?></div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <header class="top-header">
                <div style="display:flex; align-items:center;">
                    <button type="button" class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Menu">☰</button>
                    <h2><?= htmlspecialchars($title ?? 'Dashboard') ?></h2>
                </div>
                <div class="top-header-actions">
                    <?php if (Auth::check() && in_array(($active ?? ''), ['http_access', 'peers'], true)): ?>
                    <?php if (PolicyUi::simpleUnlocked()): ?>
                    <form method="POST" action="/ui/policy-mode" class="ui-mode-switch">
                        <?= View::csrf() ?>
                        <input type="hidden" name="return" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/') ?>">
                        <input type="hidden" name="mode" value="<?= PolicyUi::isSimple() ? 'expert' : 'simple' ?>">
                        <span class="ui-mode-label"><?= PolicyUi::isSimple() ? 'Simple' : 'Expert' ?></span>
                        <button type="submit" class="btn btn-sm btn-secondary"><?= PolicyUi::isSimple() ? 'Expert' : 'Simple' ?></button>
                    </form>
                    <?php elseif (Auth::isAdmin()): ?>
                    <form method="POST" action="/ui/simple-unlock" class="ui-mode-switch" onsubmit="return confirm('Simple is for rules you build in the panel (from/to, who→channel). Imported CONNECT/port rules stay in Expert and are not converted. Enable Simple?')">
                        <?= View::csrf() ?>
                        <input type="hidden" name="return" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/') ?>">
                        <span class="ui-mode-label">Expert</span>
                        <button type="submit" class="btn btn-sm btn-secondary">Enable Simple</button>
                    </form>
                    <?php else: ?>
                    <span class="ui-mode-label">Expert</span>
                    <?php endif; ?>
                    <?php endif; ?>
                    <?php if (Auth::check()): ?>
                    <div class="user-badge">
                        <span><?= htmlspecialchars($_SESSION['username'] ?? '') ?></span>
                        <span class="role"><?= htmlspecialchars($_SESSION['role'] ?? '') ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </header>
            <main class="content-area">
                <?= $content ?? '' ?>
            </main>
        </div>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    <div id="acl-tip-pop" hidden></div>
    <script>
    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('show');
    }
    (function () {
        const pop = document.getElementById('acl-tip-pop');
        if (!pop) return;
        let current = null;
        function hideTip() {
            pop.hidden = true;
            current = null;
        }
        function placeTip(el) {
            const text = el.getAttribute('data-acl-tip');
            if (!text) {
                hideTip();
                return;
            }
            current = el;
            pop.textContent = text;
            pop.hidden = false;
            const r = el.getBoundingClientRect();
            const margin = 8;
            pop.style.left = r.left + 'px';
            pop.style.top = (r.bottom + 6) + 'px';
            const pr = pop.getBoundingClientRect();
            let left = r.left;
            let top = r.bottom + 6;
            if (pr.right > window.innerWidth - margin) {
                left = Math.max(margin, window.innerWidth - pr.width - margin);
            }
            if (pr.bottom > window.innerHeight - margin) {
                top = Math.max(margin, r.top - pr.height - 6);
            }
            pop.style.left = left + 'px';
            pop.style.top = top + 'px';
        }
        document.addEventListener('mouseover', function (e) {
            const el = e.target.closest('[data-acl-tip]');
            if (!el || current === el) return;
            placeTip(el);
        });
        document.addEventListener('mouseout', function (e) {
            const el = e.target.closest('[data-acl-tip]');
            if (!el || el !== current) return;
            const to = e.relatedTarget;
            if (to && (el.contains(to) || pop.contains(to))) return;
            hideTip();
        });
        document.addEventListener('click', function (e) {
            const a = e.target.closest('a.acl-ref');
            if (!a) return;
            e.stopPropagation();
        }, true);
        window.addEventListener('scroll', hideTip, true);
        window.addEventListener('resize', hideTip);
    })();
    </script>
</body>
</html>
