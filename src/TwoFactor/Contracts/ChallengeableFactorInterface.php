<?php

declare(strict_types=1);

namespace Jengo\Auth\TwoFactor\Contracts;

use Jengo\Auth\Entities\User;

interface ChallengeableFactorInterface extends FactorDriverInterface
{
    /**
     * Prepare a challenge for the user (e.g. generate WebAuthn assertion options or send an email OTP).
     *
     * @return array<string, mixed> Client challenge payload
     */
    public function createChallenge(User $user, array $context = []): array;
}
