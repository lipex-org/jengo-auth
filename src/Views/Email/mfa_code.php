<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Two-Factor Authentication Code</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px; }
        .container { max-width: 560px; margin: 0 auto; background: #ffffff; padding: 32px; border-radius: 8px; border: 1px solid #e2e8f0; text-align: center; }
        .code-box { font-size: 32px; font-weight: 700; letter-spacing: 8px; color: #1e293b; background: #f1f5f9; padding: 16px; border-radius: 8px; margin: 24px 0; display: inline-block; min-width: 200px; }
        .footer { margin-top: 32px; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Your Verification Code</h2>
        <p>Hello <?= esc($user->username ?? 'there') ?>,</p>
        <p>Use the following 6-digit security code to complete your two-factor authentication:</p>
        <div class="code-box"><?= esc($code) ?></div>
        <p style="font-size: 14px; color: #64748b;">This code expires in 5 minutes. Never share this code with anyone.</p>
        <div class="footer">
            &copy; <?= date('Y') ?> Jengo Auth. All rights reserved.
        </div>
    </div>
</body>
</html>
