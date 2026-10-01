<?php

declare(strict_types=1);

namespace Jengo\Auth\TwoFactor\Contracts;

use Jengo\Auth\Entities\User;

interface EnrollableFactorInterface extends FactorDriverInterface
{
    /**
     * Start the enrollment process (e.g. generate Base32 TOTP secret & QR URI, or WebAuthn creation options).
     *
     * @return array<string, mixed> Enrollment initialization payload for client
     */
    public function startEnrollment(User $user, array $options = []): array;

    /**
     * Confirm enrollment with initial verification proof before permanently activating.
     *
     * @param User $user The user enrolling the factor
     * @param mixed $proof Verification proof confirming setup
     * @param array<string, mixed> $metadata Optional credential metadata (e.g. device name, key transports)
     * @return bool True if enrollment was confirmed and activated
     */
    public function confirmEnrollment(User $user, mixed $proof, array $metadata = []): bool;

    /**
     * Unenroll / remove this factor or a specific credential ID for the user.
     *
     * @param User $user The user unenrolling
     * @param string|null $credentialId Specific credential identifier (for multi-device factors like Passkeys)
     * @return bool
     */
    public function unenroll(User $user, ?string $credentialId = null): bool;
}
