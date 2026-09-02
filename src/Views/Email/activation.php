<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Activate Your Account</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px; }
        .container { max-width: 560px; margin: 0 auto; background: #ffffff; padding: 32px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .button { display: inline-block; padding: 12px 24px; background-color: #2563eb; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: 600; margin: 20px 0; }
        .footer { margin-top: 32px; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Welcome to Jengo!</h2>
        <p>Hello <?= esc($user->username ?? 'there') ?>,</p>
        <p>Thanks for creating an account. Please click the button below to verify your email address and activate your account:</p>
        <p style="text-align: center;">
            <a href="<?= esc($url) ?>" class="button">Activate Account</a>
        </p>
        <p style="font-size: 14px; color: #64748b;">Or copy and paste this URL into your browser:<br>
            <a href="<?= esc($url) ?>"><?= esc($url) ?></a>
        </p>
        <div class="footer">
            &copy; <?= date('Y') ?> Jengo Auth. All rights reserved.
        </div>
    </div>
</body>
</html>
