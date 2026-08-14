<!DOCTYPE html>
<html lang="<?= DEFAULT_LANG ?? 'ru' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Squid Proxy Manager') ?> — SPM</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/inter-font.css">
</head>
<body>
    <div class="app-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <h1>Squid Proxy Manager</h1>
                <span>Network Security</span>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="/dashboard" class="<?= ($active ?? '') === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a></li>
                    <li><a href="/acl" class="<?= ($active ?? '') === 'acl' ? 'active' : '' ?>">🛡 ACLs</a></li>
                    <li><a href="/http_access" class="<?= ($active ?? '') === 'http_access' ? 'active' : '' ?>">🔒 HTTP Access</a></li>
                    <li><a href="/peers" class="<?= ($active ?? '') === 'peers' ? 'active' : '' ?>">🌐 Cache Peers</a></li>
                    <li><a href="/auth" class="<?= ($active ?? '') === 'auth' ? 'active' : '' ?>">🔑 Authentication</a></li>
                    <li><a href="/users" class="<?= ($active ?? '') === 'users' ? 'active' : '' ?>">👤 Users</a></li>
                    <li><a href="/logs" class="<?= ($active ?? '') === 'logs' ? 'active' : '' ?>">📋 Logs</a></li>
                    <li><a href="/stats" class="<?= ($active ?? '') === 'stats' ? 'active' : '' ?>">📈 Statistics</a></li>
                    <li><a href="/backup" class="<?= ($active ?? '') === 'backup' ? 'active' : '' ?>">💾 Backup</a></li>
                    <li><a href="/scheduler" class="<?= ($active ?? '') === 'scheduler' ? 'active' : '' ?>">⏰ Scheduler</a></li>
                    <li><a href="/audit" class="<?= ($active ?? '') === 'audit' ? 'active' : '' ?>">📜 Audit</a></li>
                    <li><a href="/settings" class="<?= ($active ?? '') === 'settings' ? 'active' : '' ?>">⚙ Settings</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <?php if (Auth::check()): ?>
                <div style="margin-bottom: 8px;">
                    <strong><?= htmlspecialchars($_SESSION['user'] ?? 'Guest') ?></strong>
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
                    <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
                    <h2><?= htmlspecialchars($title ?? 'Dashboard') ?></h2>
                </div>
                <div class="top-header-actions">
                    <?php if (Auth::check()): ?>
                    <div class="user-badge">
                        <span><?= htmlspecialchars($_SESSION['user'] ?? '') ?></span>
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
    <script>
    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('show');
    }
    </script>
</body>
</html>
