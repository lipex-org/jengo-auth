<?php

declare(strict_types=1);

namespace Jengo\Auth\TwoFactor\Drivers;

use Config\Database;
use Config\Services;
use Jengo\Auth\Entities\User;
use Jengo\Auth\TwoFactor\Contracts\ChallengeableFactorInterface;
use Jengo\Auth\TwoFactor\Contracts\VerifiableFactorInterface;

class EmailOtpDriver implements ChallengeableFactorInterface, VerifiableFactorInterface
{
    public function getId(): string
    {
        return 'email_otp';
    }

    public function getLabel(): string
    {
        return 'Email Verification Code';
    }

    public function getIcon(): string
    {
        return 'mail';
    }

    public function getDescription(): string
    {
        return 'Receive a 6-digit one-time passcode delivered to your primary email address.';
    }

    public function isEnrolled(User $user): bool
    {
        // Email is available if user has an email address
        $email = $user->email ?? $user->attributes['email'] ?? null;
        if (empty($email)) {
            return false;
        }

        $userId = $user->id ?? $user->attributes['id'] ?? 0;
        $db = Database::connect();

        if (!$db->tableExists('auth_user_two_factor')) {
            return true; // Default available if table not yet migrated
        }

        $row = $db->table('auth_user_two_factor')->where('user_id', $userId)->get()->getRowArray();
        return ($row['email_otp_enabled'] ?? 1) === 1;
    }

    public function createChallenge(User $user, array $context = []): array
    {
        $code = (string) random_int(100000, 999999);
        $expiresAt = time() + 600; // 10 minutes

        $hashedCode = hash('sha256', $code);

        // Store active challenge in session
        session()->set('auth_two_factor_email_otp', [
            'hash'       => $hashedCode,
            'expires_at' => $expiresAt,
            'user_id'    => $user->id ?? $user->attributes['id'] ?? 0,
        ]);

        // Send email via configured auth notifier
        $auth = Services::auth();
        $notifier = $auth->getNotifier();

        $email = (string) ($user->email ?? $user->attributes['email'] ?? '');
        if ($email && $notifier) {
            $emailConfig = config('Auth')->emailConfig ?? [];
            $fromEmail = $emailConfig['fromEmail'] ?? 'noreply@example.com';
            $fromName = $emailConfig['fromName'] ?? 'Jengo Auth';

            $viewName = config('Auth')->emailViews['mfaCode'] ?? 'Jengo\Auth\Views\Email\mfa_code';
            $body = view($viewName, ['code' => $code, 'user' => $user]);

            $notifier->send($email, 'Your Verification Code: ' . $code, $body, $fromEmail, $fromName);
        }

        // Mask email for display: a***e@example.com
        $maskedEmail = $this->maskEmail($email);

        return [
            'type'         => 'email_otp',
            'label'        => $this->getLabel(),
            'description'  => "A 6-digit code has been sent to {$maskedEmail}.",
            'masked_email' => $maskedEmail,
            'expires_in'   => 600,
        ];
    }

    public function verify(User $user, mixed $proof, array $context = []): bool
    {
        $code = is_string($proof) ? trim($proof) : (string) ($proof['code'] ?? '');
        if ($code === '') {
            return false;
        }

        $sessionData = session()->get('auth_two_factor_email_otp');
        if (!is_array($sessionData) || empty($sessionData['hash']) || empty($sessionData['expires_at'])) {
            return false;
        }

        if (time() > (int) $sessionData['expires_at']) {
            session()->remove('auth_two_factor_email_otp');
            return false;
        }

        $userId = $user->id ?? $user->attributes['id'] ?? 0;
        if ((int) $sessionData['user_id'] !== (int) $userId) {
            return false;
        }

        $submittedHash = hash('sha256', $code);
        if (!hash_equals($sessionData['hash'], $submittedHash)) {
            return false;
        }

        session()->remove('auth_two_factor_email_otp');

        return true;
    }

    protected function maskEmail(string $email): string
    {
        if (!str_contains($email, '@')) {
            return $email;
        }
        [$local, $domain] = explode('@', $email, 2);
        if (strlen($local) <= 2) {
            $maskedLocal = substr($local, 0, 1) . '*';
        } else {
            $maskedLocal = substr($local, 0, 1) . str_repeat('*', strlen($local) - 2) . substr($local, -1);
        }
        return "{$maskedLocal}@{$domain}";
    }
}
