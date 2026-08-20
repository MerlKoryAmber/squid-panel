<!DOCTYPE html>
<html lang="<?= DEFAULT_LANG ?? 'ru' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Squid Proxy Manager</title>
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
<body class="login-page">
    <div class="login-box">
        <div class="logo">
            <div class="brand-mark brand-mark-lg" aria-hidden="true">
                <img src="<?= htmlspecialchars(View::asset('/assets/img/spm-mascot.png')) ?>" alt="" width="78" height="44">
            </div>
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
