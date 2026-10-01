<?php

declare(strict_types=1);

namespace Jengo\Auth\TwoFactor\Contracts;

use Jengo\Auth\Entities\User;

interface VerifiableFactorInterface extends FactorDriverInterface
{
    /**
     * Verify the user's submitted proof (e.g. 6-digit TOTP code, signed WebAuthn payload, password string).
     *
     * @param User $user The user being challenged
     * @param mixed $proof The submitted proof from client
     * @param array<string, mixed> $context Additional context (e.g. session challenge data)
     * @return bool True if verification succeeds, false otherwise
     */
    public function verify(User $user, mixed $proof, array $context = []): bool;
}
