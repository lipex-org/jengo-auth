<?php

declare(strict_types=1);

namespace Jengo\Auth\TwoFactor\Drivers;

use Jengo\Auth\Entities\User;
use Jengo\Auth\TwoFactor\Contracts\VerifiableFactorInterface;

class PasswordDriver implements VerifiableFactorInterface
{
    public function getId(): string
    {
        return 'password';
    }

    public function getLabel(): string
    {
        return 'Account Password';
    }

    public function getIcon(): string
    {
        return 'lock';
    }

    public function getDescription(): string
    {
        return 'Confirm your current account password.';
    }

    public function isEnrolled(User $user): bool
    {
        return $this->resolvePasswordHash($user) !== null;
    }

    public function verify(User $user, mixed $proof, array $context = []): bool
    {
        $password = is_string($proof) ? $proof : (string) ($proof['password'] ?? '');
        if ($password === '') {
            return false;
        }

        $hash = $this->resolvePasswordHash($user);
        if ($hash === null || $hash === '') {
            return false;
        }

        return password_verify($password, $hash);
    }

    protected function resolvePasswordHash(User $user): ?string
    {
        if (!empty($user->password_hash ?? $user->attributes['password_hash'] ?? null)) {
            return (string) ($user->password_hash ?? $user->attributes['password_hash']);
        }

        $userId = $user->id ?? $user->attributes['id'] ?? 0;
        if (!$userId) {
            return null;
        }

        $db = \Config\Database::connect();
        $tableName = $db->tableExists('user_identities') ? 'user_identities' : ($db->tableExists('auth_identities') ? 'auth_identities' : null);
        if (!$tableName) {
            return null;
        }

        $row = $db->table($tableName)
            ->where('user_id', $userId)
            ->where('type', 'email_password')
            ->get()
            ->getRowArray();

        return $row['secret'] ?? null;
    }
}
