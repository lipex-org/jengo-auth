<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Magic Login Link</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px; }
        .container { max-width: 560px; margin: 0 auto; background: #ffffff; padding: 32px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .button { display: inline-block; padding: 12px 24px; background-color: #2563eb; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: 600; margin: 20px 0; }
        .footer { margin-top: 32px; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Log in to your account</h2>
        <p>Hello <?= esc($user->username ?? 'there') ?>,</p>
        <p>You requested a one-click login link. Click the button below to log in securely:</p>
        <p style="text-align: center;">
            <a href="<?= esc($url) ?>" class="button">Log In Now</a>
        </p>
        <p style="font-size: 14px; color: #64748b;">Or copy and paste this URL into your browser:<br>
            <a href="<?= esc($url) ?>"><?= esc($url) ?></a>
        </p>
        <p style="font-size: 14px; color: #64748b;">This link is valid for 15 minutes. If you did not request this email, you can safely ignore it.</p>
        <div class="footer">
            &copy; <?= date('Y') ?> Jengo Auth. All rights reserved.
        </div>
    </div>
</body>
</html>
