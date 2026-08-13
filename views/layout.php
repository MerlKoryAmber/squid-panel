<!DOCTYPE html>
<html lang="<?= DEFAULT_LANG ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Squid Proxy Manager') ?> — SPM</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <div class="logo">
                <span>Squid Panel</span>
            </div>
            <nav class="nav">
                <a href="/dashboard" class="nav-item<?= (strpos($_SERVER['REQUEST_URI'], '/dashboard') === 0 || $_SERVER['REQUEST_URI'] === '/') ? ' active' : '' ?>">Dashboard</a>
                <a href="/acl" class="nav-item<?= strpos($_SERVER['REQUEST_URI'], '/acl') === 0 ? ' active' : '' ?>">ACLs</a>
                <a href="/http_access" class="nav-item<?= strpos($_SERVER['REQUEST_URI'], '/http_access') === 0 ? ' active' : '' ?>">Access Rules</a>
                <a href="/peers" class="nav-item<?= strpos($_SERVER['REQUEST_URI'], '/peers') === 0 ? ' active' : '' ?>">Cache Peers</a>
                <a href="/auth/kerberos" class="nav-item<?= strpos($_SERVER['REQUEST_URI'], '/auth') === 0 ? ' active' : '' ?>">Auth</a>
                <a href="/users" class="nav-item<?= strpos($_SERVER['REQUEST_URI'], '/users') === 0 ? ' active' : '' ?>">Users</a>
                <a href="/logs" class="nav-item<?= strpos($_SERVER['REQUEST_URI'], '/logs') === 0 ? ' active' : '' ?>">Logs</a>
                <a href="/stats" class="nav-item<?= strpos($_SERVER['REQUEST_URI'], '/stats') === 0 ? ' active' : '' ?>">Stats</a>
                <a href="/backup" class="nav-item<?= strpos($_SERVER['REQUEST_URI'], '/backup') === 0 ? ' active' : '' ?>">Backups</a>
                <a href="/scheduler" class="nav-item<?= strpos($_SERVER['REQUEST_URI'], '/scheduler') === 0 ? ' active' : '' ?>">Scheduler</a>
                <a href="/audit" class="nav-item<?= strpos($_SERVER['REQUEST_URI'], '/audit') === 0 ? ' active' : '' ?>">Audit</a>
                <a href="/settings" class="nav-item<?= strpos($_SERVER['REQUEST_URI'], '/settings') === 0 ? ' active' : '' ?>">Settings</a>
            </nav>
            <div class="sidebar-footer">
                <span><?= htmlspecialchars(Auth::user()['username'] ?? 'guest') ?></span>
                <a href="/logout" class="nav-item">Logout</a>
            </div>
        </aside>
        <main class="main">
            <header class="topbar">
                <h1><?= htmlspecialchars($title ?? '') ?></h1>
            </header>
            <div class="content">
                <?= $content ?? '' ?>
            </div>
        </main>
    </div>
    <script src="/assets/js/app.js"></script>
</body>
</html>
