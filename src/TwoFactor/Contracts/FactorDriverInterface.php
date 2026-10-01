<?php

declare(strict_types=1);

namespace Jengo\Auth\TwoFactor\Contracts;

use Jengo\Auth\Entities\User;

interface FactorDriverInterface
{
    /**
     * Unique factor identifier: 'passkey', 'totp', 'email_otp', 'recovery_code', 'password', etc.
     */
    public function getId(): string;

    /**
     * Human-readable display label (e.g. "Security Key / Passkey", "Authenticator App").
     */
    public function getLabel(): string;

    /**
     * Icon or UI identifier for frontend rendering.
     */
    public function getIcon(): string;

    /**
     * Short description of how this factor authenticates.
     */
    public function getDescription(): string;

    /**
     * Check if the given user has enrolled / enabled this factor.
     */
    public function isEnrolled(User $user): bool;
}
