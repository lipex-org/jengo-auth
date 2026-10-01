<?php

declare(strict_types=1);

namespace Jengo\Auth\TwoFactor\Drivers;

use Config\Database;
use Jengo\Auth\Entities\User;
use Jengo\Auth\TwoFactor\Contracts\ChallengeableFactorInterface;
use Jengo\Auth\TwoFactor\Contracts\EnrollableFactorInterface;
use Jengo\Auth\TwoFactor\Contracts\VerifiableFactorInterface;
use Jengo\Auth\TwoFactor\Engines\TotpEngine;

class TotpDriver implements ChallengeableFactorInterface, VerifiableFactorInterface, EnrollableFactorInterface
{
    public function getId(): string
    {
        return 'totp';
    }

    public function getLabel(): string
    {
        return 'Authenticator App';
    }

    public function getIcon(): string
    {
        return 'smartphone';
    }

    public function getDescription(): string
    {
        return 'Enter a 6-digit rolling verification code from Google Authenticator, 1Password, or Authy.';
    }

    public function isEnrolled(User $user): bool
    {
        $userId = $this->resolveUserId($user);
        $db = Database::connect();
        
        if (!$db->tableExists('auth_user_two_factor')) {
            return false;
        }

        $row = $db->table('auth_user_two_factor')
            ->where('user_id', $userId)
            ->where('totp_enabled', 1)
            ->get()
            ->getRowArray();

        return !empty($row['totp_secret']);
    }

    public function createChallenge(User $user, array $context = []): array
    {
        return [
            'type'        => 'totp',
            'label'       => $this->getLabel(),
            'description' => $this->getDescription(),
            'digits'      => 6,
        ];
    }

    public function verify(User $user, mixed $proof, array $context = []): bool
    {
        $code = is_string($proof) ? trim($proof) : (string) ($proof['code'] ?? '');
        if ($code === '') {
            return false;
        }

        $secret = $this->getStoredSecret($user);
        if (!$secret) {
            return false;
        }

        return TotpEngine::verify($code, $secret);
    }

    public function startEnrollment(User $user, array $options = []): array
    {
        $secret = TotpEngine::generateSecret();
        $appName = (string) (config('App')->appName ?? config('Auth')->emailConfig['fromName'] ?? 'Jengo App');
        $accountName = (string) ($user->email ?? $user->username ?? 'user');

        $otpAuthUri = TotpEngine::getOtpAuthUri($secret, $accountName, $appName);

        // Temporarily store pending secret in session
        session()->set('auth_two_factor_pending_totp_secret', $secret);

        return [
            'secret'      => $secret,
            'qr_uri'      => $otpAuthUri,
            'account'     => $accountName,
            'issuer'      => $appName,
            'digits'      => 6,
            'period'      => 30,
        ];
    }

    public function confirmEnrollment(User $user, mixed $proof, array $metadata = []): bool
    {
        $code = is_string($proof) ? trim($proof) : (string) ($proof['code'] ?? '');
        $secret = session()->get('auth_two_factor_pending_totp_secret');

        if (!$secret || !TotpEngine::verify($code, $secret)) {
            return false;
        }

        $userId = $this->resolveUserId($user);
        $db = Database::connect();

        $existing = $db->table('auth_user_two_factor')->where('user_id', $userId)->get()->getRowArray();

        $data = [
            'totp_secret'  => $secret,
            'totp_enabled' => 1,
            'updated_at'   => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $db->table('auth_user_two_factor')->where('user_id', $userId)->update($data);
        } else {
            $data['user_id'] = $userId;
            $data['created_at'] = date('Y-m-d H:i:s');
            $db->table('auth_user_two_factor')->insert($data);
        }

        session()->remove('auth_two_factor_pending_totp_secret');

        return true;
    }

    public function unenroll(User $user, ?string $credentialId = null): bool
    {
        $userId = $this->resolveUserId($user);
        $db = Database::connect();

        return $db->table('auth_user_two_factor')
            ->where('user_id', $userId)
            ->update([
                'totp_secret'  => null,
                'totp_enabled' => 0,
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
    }

    protected function getStoredSecret(User $user): ?string
    {
        $userId = $this->resolveUserId($user);
        $db = Database::connect();

        $row = $db->table('auth_user_two_factor')
            ->where('user_id', $userId)
            ->where('totp_enabled', 1)
            ->get()
            ->getRowArray();

        return $row['totp_secret'] ?? null;
    }

    protected function resolveUserId(User $user): int|string
    {
        return $user->id ?? $user->attributes['id'] ?? 0;
    }
}
