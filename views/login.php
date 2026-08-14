<!DOCTYPE html>
<html lang="<?= DEFAULT_LANG ?? 'ru' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Squid Proxy Manager</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-box">
        <div class="logo">
            <h1>Squid Proxy Manager</h1>
            <span>Network Security & Access Control</span>
        </div>

        <?php if (!empty($error)): ?>
        <div class="alert alert-danger" style="margin-bottom: var(--space-lg);">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="/login">
            <?= View::csrf() ?>
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter username" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter password" required>
            </div>
            <button type="submit" class="btn btn-primary">Sign In</button>
        </form>

        <p class="text-center text-muted" style="margin-top: var(--space-lg); font-size: 0.8rem;">
            Squid Proxy Manager v<?= SPM_VERSION ?>
        </p>
    </div>
</body>
</html>
