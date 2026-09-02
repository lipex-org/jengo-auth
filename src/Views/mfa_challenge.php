<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Verification - Jengo Auth</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f8fafc; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); width: 100%; max-width: 400px; text-align: center; }
        .input-group { margin: 1.5rem 0; }
        label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; }
        input[type="text"] { width: 100%; padding: 0.75rem; font-size: 1.5rem; text-align: center; letter-spacing: 0.5rem; border: 1px solid #cbd5e1; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 0.625rem; background: #3b82f6; color: #fff; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; }
        button:hover { background: #2563eb; }
        .info { color: #64748b; font-size: 0.875rem; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Two-Factor Verification</h2>
        <p class="info">Please enter the verification code sent to your registered contact.</p>
        <form action="<?= url_to('auth.action.handle') ?>" method="post">
            <?= csrf_field() ?>
            <div class="input-group">
                <label for="code">Security Code</label>
                <input type="text" id="code" name="code" maxlength="6" pattern="[0-9]{6}" required autofocus placeholder="123456">
            </div>
            <button type="submit">Verify & Continue</button>
        </form>
    </div>
</body>
</html>
