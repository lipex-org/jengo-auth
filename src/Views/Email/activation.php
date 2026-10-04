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
        <h2>Welcome to <?= esc(config('Auth')->branding['name'] ?? 'Jengo') ?>!</h2>
        <p>Hello <?= esc($user->username ?? 'there') ?>,</p>
        <p>Thanks for creating an account. Use the following 6-digit security code to activate your account:</p>
        <div style="font-size: 32px; font-weight: 700; letter-spacing: 8px; color: #1e293b; background: #f1f5f9; padding: 16px; border-radius: 8px; margin: 24px 0; text-align: center;">
            <?= esc($token ?? $code ?? '') ?>
        </div>
        <p style="font-size: 14px; color: #64748b; text-align: center;">This code expires in 30 minutes.</p>
        <?php if (! empty($url)): ?>
            <p style="text-align: center; margin-top: 24px;">
                <a href="<?= esc($url) ?>" class="button">Go to Activation Page</a>
            </p>
        <?php endif; ?>
        <div class="footer">
            &copy; <?= date('Y') ?> <?= esc(config('Auth')->branding['companyName'] ?? config('Auth')->branding['name'] ?? 'Jengo Auth') ?>. All rights reserved.
        </div>
    </div>
</body>
</html>
