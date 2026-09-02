<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In - Jengo Auth</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f8fafc; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); width: 100%; max-width: 400px; }
        .input-group { margin-bottom: 1rem; }
        label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem; }
        input[type="text"], input[type="email"], input[type="password"] { width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 0.625rem; background: #3b82f6; color: #fff; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; }
        button:hover { background: #2563eb; }
        .error { color: #ef4444; font-size: 0.875rem; margin-bottom: 1rem; }
        .links { margin-top: 1rem; display: flex; justify-content: space-between; font-size: 0.875rem; }
        a { color: #3b82f6; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Log In</h2>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="error"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>
        <form action="<?= url_to('login.attempt') ?>" method="post">
            <?= csrf_field() ?>
            <div class="input-group">
                <label for="email">Email or Username</label>
                <input type="text" id="email" name="email" value="<?= old('email') ?>" required autofocus>
            </div>
            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="input-group" style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" id="remember" name="remember" value="1">
                <label for="remember" style="margin-bottom: 0;">Remember me</label>
            </div>
            <button type="submit">Log In</button>
        </form>
        <div class="links">
            <a href="<?= url_to('register') ?>">Create an account</a>
            <a href="<?= url_to('forgot-password') ?>">Forgot password?</a>
        </div>
    </div>
</body>
</html>
