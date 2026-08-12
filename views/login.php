<!DOCTYPE html>
<html lang="<?= DEFAULT_LANG ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Squid Proxy Manager</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        .login-page { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: var(--kimi-color-surface-secondary); }
        .login-box { width: 100%; max-width: 380px; padding: 32px; background: var(--kimi-color-surface-primary); border: 1px solid var(--kimi-color-border-secondary); border-radius: 12px; }
        .login-logo { display: flex; align-items: center; gap: 10px; margin-bottom: 24px; font-size: 20px; font-weight: 500; }
        .login-logo svg { color: var(--kimi-color-accent); }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 500; color: var(--kimi-color-text-secondary); }
        .form-group input { width: 100%; padding: 10px 12px; border: 1px solid var(--kimi-color-border-secondary); border-radius: 8px; background: transparent; font-size: 14px; color: var(--kimi-color-text-primary); }
        .form-group input:focus { outline: none; border-color: var(--kimi-color-text-primary); }
        .btn-primary { width: 100%; padding: 10px; border: none; border-radius: 8px; background: var(--kimi-color-text-primary); color: var(--kimi-color-surface-primary); font-size: 14px; font-weight: 500; cursor: pointer; }
        .btn-primary:hover { opacity: 0.9; }
        .alert { padding: 10px 12px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; background: color-mix(in srgb, var(--kimi-color-danger) 10%, transparent); color: var(--kimi-color-danger); border: 1px solid color-mix(in srgb, var(--kimi-color-danger) 20%, transparent); }
    </style>
</head>
<body>
    <div class="login-page">
        <div class="login-box">
            <div class="login-logo">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Squid Proxy Manager
            </div>

            <?php if (!empty($error)): ?>
            <div class="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="/login">
                <?= View::csrf() ?>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required autofocus>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" class="btn-primary">Sign in</button>
            </form>
            <div style="margin-top: 16px; font-size: 12px; color: var(--kimi-color-text-tertiary); text-align: center;">
                Default: admin / admin
            </div>
        </div>
    </div>
</body>
</html>
