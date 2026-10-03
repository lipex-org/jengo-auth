<?php

declare(strict_types=1);

namespace Jengo\Auth\TwoFactor\Drivers;

use Config\Database;
use Config\Services;
use Jengo\Auth\Entities\User;
use Jengo\Auth\TwoFactor\Contracts\ChallengeableFactorInterface;
use Jengo\Auth\TwoFactor\Contracts\EnrollableFactorInterface;
use Jengo\Auth\TwoFactor\Contracts\VerifiableFactorInterface;
use Jengo\Auth\TwoFactor\Engines\WebAuthnEngine;

class PasskeyDriver implements ChallengeableFactorInterface, VerifiableFactorInterface, EnrollableFactorInterface
{
    public function getId(): string
    {
        return 'passkey';
    }

    public function getLabel(): string
    {
        return 'Passkey / Security Key';
    }

    public function getIcon(): string
    {
        return 'fingerprint';
    }

    public function getDescription(): string
    {
        return 'Authenticate with Touch ID, Face ID, Windows Hello, or a hardware security key.';
    }

    public function isEnrolled(User $user): bool
    {
        $userId = $this->resolveUserId($user);
        $db = Database::connect();

        if (!$db->tableExists('auth_user_passkeys')) {
            return false;
        }

        $count = $db->table('auth_user_passkeys')
            ->where('user_id', $userId)
            ->countAllResults();

        return $count > 0;
    }

    public function createChallenge(User $user, array $context = []): array
    {
        $userId = $this->resolveUserId($user);
        $db = Database::connect();

        $rows = $db->table('auth_user_passkeys')
            ->where('user_id', $userId)
            ->get()
            ->getResultArray();

        $allowCredentials = [];
        foreach ($rows as $row) {
            $transports = [];
            if (!empty($row['transports'])) {
                $decoded = json_decode($row['transports'], true);
                if (is_array($decoded)) {
                    $transports = $decoded;
                }
            }
            $allowCredentials[] = [
                'id'         => $row['credential_id'],
                'transports' => $transports,
            ];
        }

        $rpId = $this->resolveRpId();
        $options = WebAuthnEngine::generateRequestOptions($allowCredentials, $rpId);

        // Store active challenge in session
        session()->set('auth_two_factor_passkey_challenge', $options['challenge']);

        return [
            'type'        => 'passkey',
            'label'       => $this->getLabel(),
            'description' => $this->getDescription(),
            'options'     => $options,
        ];
    }

    public function verify(User $user, mixed $proof, array $context = []): bool
    {
        if (!is_array($proof) || empty($proof['id'])) {
            return false;
        }

        $expectedChallenge = (string) session()->get('auth_two_factor_passkey_challenge');
        if (!$expectedChallenge) {
            return false;
        }

        $userId = $this->resolveUserId($user);
        $db = Database::connect();

        $credential = $db->table('auth_user_passkeys')
            ->where('user_id', $userId)
            ->where('credential_id', $proof['id'])
            ->get()
            ->getRowArray();

        if (!$credential) {
            return false;
        }

        $rpId = $this->resolveRpId();
        $prevCounter = (int) ($credential['counter'] ?? 0);

        $result = WebAuthnEngine::verifyAssertionResponse(
            $proof,
            $expectedChallenge,
            $credential['public_key'],
            $rpId,
            $prevCounter
        );

        if (!$result['verified']) {
            return false;
        }

        // Update signature counter and last_used_at timestamp
        $db->table('auth_user_passkeys')
            ->where('id', $credential['id'])
            ->update([
                'counter'      => $result['newCounter'],
                'last_used_at' => date('Y-m-d H:i:s'),
            ]);

        session()->remove('auth_two_factor_passkey_challenge');

        return true;
    }

    public function startEnrollment(User $user, array $options = []): array
    {
        $userId = $this->resolveUserId($user);
        $db = Database::connect();

        $existingCreds = [];
        if ($db->tableExists('auth_user_passkeys')) {
            $existing = $db->table('auth_user_passkeys')
                ->select('credential_id')
                ->where('user_id', $userId)
                ->get()
                ->getResultArray();
            $existingCreds = array_column($existing, 'credential_id');
        }

        $rpName = (string) (config('App')->appName ?? config('Email')->fromName ?? 'Jengo App');
        $rpId = $this->resolveRpId();

        $creationOptions = WebAuthnEngine::generateCreationOptions(
            $user,
            $rpName,
            $rpId,
            $existingCreds
        );

        session()->set('auth_two_factor_pending_passkey_challenge', $creationOptions['challenge']);

        return [
            'options' => $creationOptions,
            'rpId'    => $rpId,
            'rpName'  => $rpName,
        ];
    }

    public function confirmEnrollment(User $user, mixed $proof, array $metadata = []): bool
    {
        if (!is_array($proof)) {
            return false;
        }

        $expectedChallenge = (string) session()->get('auth_two_factor_pending_passkey_challenge');
        if (!$expectedChallenge) {
            return false;
        }

        $rpId = $this->resolveRpId();

        try {
            $parsed = WebAuthnEngine::parseRegistrationResponse(
                $proof,
                $expectedChallenge,
                $rpId
            );
        } catch (\Throwable) {
            return false;
        }

        $userId = $this->resolveUserId($user);
        $db = Database::connect();

        $name = !empty($metadata['name']) ? (string) $metadata['name'] : 'Passkey (' . date('M j, Y') . ')';

        $db->table('auth_user_passkeys')->insert([
            'user_id'       => $userId,
            'name'          => $name,
            'credential_id' => $parsed['credentialId'],
            'public_key'    => $parsed['publicKeyPem'],
            'counter'       => 0,
            'aaguid'        => $parsed['aaguid'],
            'transports'    => json_encode($parsed['transports']),
            'created_at'    => date('Y-m-d H:i:s'),
            'last_used_at'  => date('Y-m-d H:i:s'),
        ]);

        session()->remove('auth_two_factor_pending_passkey_challenge');

        return true;
    }

    public function unenroll(User $user, ?string $credentialId = null): bool
    {
        $userId = $this->resolveUserId($user);
        $db = Database::connect();

        $builder = $db->table('auth_user_passkeys')->where('user_id', $userId);
        if ($credentialId !== null) {
            $builder->where('credential_id', $credentialId);
        }

        return $builder->delete();
    }

    protected function resolveRpId(): string
    {
        $host = Services::request()->getUri()->getHost();
        if (!$host || $host === 'localhost' || $host === '127.0.0.1') {
            return 'localhost';
        }
        return $host;
    }

    protected function resolveUserId(User $user): int|string
    {
        return $user->id ?? $user->attributes['id'] ?? 0;
    }
}
