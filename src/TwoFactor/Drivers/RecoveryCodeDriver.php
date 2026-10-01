<?php

declare(strict_types=1);

namespace Jengo\Auth\TwoFactor\Drivers;

use Config\Database;
use Jengo\Auth\Entities\User;
use Jengo\Auth\TwoFactor\Contracts\EnrollableFactorInterface;
use Jengo\Auth\TwoFactor\Contracts\VerifiableFactorInterface;
use Jengo\Auth\TwoFactor\Engines\RecoveryCodeEngine;

class RecoveryCodeDriver implements VerifiableFactorInterface, EnrollableFactorInterface
{
    public function getId(): string
    {
        return 'recovery_code';
    }

    public function getLabel(): string
    {
        return 'Emergency Recovery Code';
    }

    public function getIcon(): string
    {
        return 'key';
    }

    public function getDescription(): string
    {
        return 'Use one of your emergency single-use backup recovery codes.';
    }

    public function isEnrolled(User $user): bool
    {
        $userId = $this->resolveUserId($user);
        $db = Database::connect();

        if (!$db->tableExists('auth_user_two_factor')) {
            return false;
        }

        $row = $db->table('auth_user_two_factor')->where('user_id', $userId)->get()->getRowArray();
        if (empty($row['recovery_codes'])) {
            return false;
        }

        $codes = json_decode($row['recovery_codes'], true);
        return is_array($codes) && count($codes) > 0;
    }

    public function verify(User $user, mixed $proof, array $context = []): bool
    {
        $code = is_string($proof) ? trim($proof) : (string) ($proof['code'] ?? '');
        if ($code === '') {
            return false;
        }

        $userId = $this->resolveUserId($user);
        $db = Database::connect();

        $row = $db->table('auth_user_two_factor')->where('user_id', $userId)->get()->getRowArray();
        if (empty($row['recovery_codes'])) {
            return false;
        }

        $storedCodes = json_decode($row['recovery_codes'], true);
        if (!is_array($storedCodes)) {
            return false;
        }

        if (!RecoveryCodeEngine::verifyAndConsume($code, $storedCodes)) {
            return false;
        }

        // Update remaining codes in DB
        $db->table('auth_user_two_factor')
            ->where('user_id', $userId)
            ->update([
                'recovery_codes' => json_encode($storedCodes),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);

        return true;
    }

    public function startEnrollment(User $user, array $options = []): array
    {
        $count = $options['count'] ?? 8;
        $plainCodes = RecoveryCodeEngine::generate((int) $count);
        $hashedCodes = RecoveryCodeEngine::hashCodes($plainCodes);

        // Store generated codes in pending session state
        session()->set('auth_two_factor_pending_recovery_codes', [
            'plain'  => $plainCodes,
            'hashed' => $hashedCodes,
        ]);

        return [
            'codes' => $plainCodes,
            'count' => count($plainCodes),
        ];
    }

    public function confirmEnrollment(User $user, mixed $proof = null, array $metadata = []): bool
    {
        $pending = session()->get('auth_two_factor_pending_recovery_codes');
        if (!is_array($pending) || empty($pending['hashed'])) {
            return false;
        }

        $userId = $this->resolveUserId($user);
        $db = Database::connect();

        $existing = $db->table('auth_user_two_factor')->where('user_id', $userId)->get()->getRowArray();

        $data = [
            'recovery_codes' => json_encode($pending['hashed']),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $db->table('auth_user_two_factor')->where('user_id', $userId)->update($data);
        } else {
            $data['user_id'] = $userId;
            $data['created_at'] = date('Y-m-d H:i:s');
            $db->table('auth_user_two_factor')->insert($data);
        }

        session()->remove('auth_two_factor_pending_recovery_codes');

        return true;
    }

    public function unenroll(User $user, ?string $credentialId = null): bool
    {
        $userId = $this->resolveUserId($user);
        $db = Database::connect();

        return $db->table('auth_user_two_factor')
            ->where('user_id', $userId)
            ->update([
                'recovery_codes' => null,
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
    }

    protected function resolveUserId(User $user): int|string
    {
        return $user->id ?? $user->attributes['id'] ?? 0;
    }
}
