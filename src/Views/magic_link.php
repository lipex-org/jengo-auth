<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magic Link Login - Jengo Auth</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f8fafc; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); width: 100%; max-width: 400px; }
        .input-group { margin-bottom: 1rem; }
        label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem; }
        input[type="email"] { width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 0.625rem; background: #3b82f6; color: #fff; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; }
        button:hover { background: #2563eb; }
        .success { color: #16a34a; font-size: 0.875rem; margin-bottom: 1rem; }
        .links { margin-top: 1rem; text-align: center; font-size: 0.875rem; }
        a { color: #3b82f6; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Passwordless Login</h2>
        <p style="font-size: 0.875rem; color: #64748b;">Enter your email to receive a secure one-click sign-in link.</p>
        <?php if (session()->getFlashdata('message')): ?>
            <div class="success"><?= esc(session()->getFlashdata('message')) ?></div>
        <?php endif; ?>
        <form action="<?= url_to('magic-link.send') ?>" method="post">
            <?= csrf_field() ?>
            <div class="input-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?= old('email') ?>" required autofocus>
            </div>
            <button type="submit">Send Magic Link</button>
        </form>
        <div class="links">
            <a href="<?= url_to('login') ?>">Back to Password Login</a>
        </div>
    </div>
</body>
</html>
