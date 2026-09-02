<?php

declare(strict_types=1);

namespace Jengo\Auth\Notifications;

use Config\Services;
use Jengo\Auth\Contracts\NotificationSenderInterface;
use Jengo\Auth\Entities\User;

class DefaultEmailNotifier implements NotificationSenderInterface
{
    public function sendMagicLink(User $user, string $token, string $url): bool
    {
        $subject = 'Your Magic Login Link';
        $view = config('Auth')->emailViews['magicLink'] ?? 'Jengo\Auth\Views\Email\magic_link';

        return $this->sendEmail($user, $subject, $view, [
            'user'  => $user,
            'token' => $token,
            'url'   => $url,
        ]);
    }

    public function sendPasswordReset(User $user, string $token, string $url): bool
    {
        $subject = 'Reset Your Password';
        $view = config('Auth')->emailViews['passwordReset'] ?? 'Jengo\Auth\Views\Email\password_reset';

        return $this->sendEmail($user, $subject, $view, [
            'user'  => $user,
            'token' => $token,
            'url'   => $url,
        ]);
    }

    public function sendMfaCode(User $user, string $code): bool
    {
        $subject = 'Your Two-Factor Authentication Code';
        $view = config('Auth')->emailViews['mfaCode'] ?? 'Jengo\Auth\Views\Email\mfa_code';

        return $this->sendEmail($user, $subject, $view, [
            'user' => $user,
            'code' => $code,
        ]);
    }

    public function sendActivation(User $user, string $token, string $url): bool
    {
        $subject = 'Activate Your Account';
        $view = config('Auth')->emailViews['activation'] ?? 'Jengo\Auth\Views\Email\activation';

        return $this->sendEmail($user, $subject, $view, [
            'user'  => $user,
            'token' => $token,
            'url'   => $url,
        ]);
    }

    public function sendNotification(string $type, User $user, array $data = []): bool
    {
        $subject = $data['subject'] ?? 'Notification from Jengo Auth';
        $view = config('Auth')->emailViews[$type] ?? null;

        if (! $view) {
            return false;
        }

        return $this->sendEmail($user, $subject, $view, array_merge(['user' => $user], $data));
    }

    protected function sendEmail(User $user, string $subject, string $viewName, array $data): bool
    {
        $emailAddress = $user->getEmail();
        if (! $emailAddress) {
            return false;
        }

        $config = config('Auth');
        $fromEmail = $config->emailConfig['fromEmail'] ?? 'noreply@example.com';
        $fromName  = $config->emailConfig['fromName'] ?? 'Jengo Auth';

        try {
            $htmlBody = view($viewName, $data);
        } catch (\Throwable $e) {
            $htmlBody = "Hello {$user->username}, please visit: " . ($data['url'] ?? $data['code'] ?? '');
        }

        $email = Services::email();
        $email->setFrom($fromEmail, $fromName);
        $email->setTo($emailAddress);
        $email->setSubject($subject);
        $email->setMessage($htmlBody);
        $email->setMailType('html');

        try {
            return (bool) $email->send(false);
        } catch (\Throwable $e) {
            log_message('error', 'Auth email sending error: ' . $e->getMessage());
            return false;
        }
    }
}
