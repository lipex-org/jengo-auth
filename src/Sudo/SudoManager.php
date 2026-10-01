<?php

declare(strict_types=1);

namespace Jengo\Auth\Sudo;

use Config\Services;
use Jengo\Auth\Entities\User;
use Jengo\Auth\TwoFactor\TwoFactorManager;

class SudoManager
{
    public const SESSION_ENTERED_AT = 'auth_sudo_entered_at';
    public const SESSION_EXPIRES_AT = 'auth_sudo_expires_at';
    public const SESSION_FACTOR     = 'auth_sudo_factor';
    public const SESSION_INTENDED   = 'auth_sudo_intended_url';

    public function __construct(
        protected ?TwoFactorManager $twoFactor = null
    ) {
        $this->twoFactor ??= Services::twoFactor();
    }

    /**
     * Check if Sudo mode is currently active for the authenticated session.
     *
     * @param int|string|null $lifetime Optional max lifetime override
     * @param bool $forceFresh If true, ignores active grace period
     */
    public function check(int|string|null $lifetime = null, bool $forceFresh = false): bool
    {
        if ($forceFresh) {
            return false;
        }

        $session = Services::session();
        $enteredAt = $session->get(self::SESSION_ENTERED_AT);
        $expiresAt = $session->get(self::SESSION_EXPIRES_AT);

        if (!$enteredAt || !$expiresAt) {
            return false;
        }

        $now = time();
        if ($now > (int) $expiresAt) {
            $this->deactivate();
            return false;
        }

        if ($lifetime !== null) {
            $seconds = $this->parseLifetime($lifetime);
            if (($now - (int) $enteredAt) > $seconds) {
                return false;
            }
        }

        return true;
    }

    /**
     * Activate Sudo mode for the current session.
     *
     * @param int|string $lifetime Grace period duration (default: 7200 seconds / 2 hours)
     */
    public function activate(int|string $lifetime = 7200, ?string $factor = null): void
    {
        $session = Services::session();
        $seconds = $this->parseLifetime($lifetime);

        $now = time();
        $session->set(self::SESSION_ENTERED_AT, $now);
        $session->set(self::SESSION_EXPIRES_AT, $now + $seconds);

        if ($factor !== null) {
            $session->set(self::SESSION_FACTOR, $factor);
        }
    }

    /**
     * Deactivate / Exit Sudo mode immediately.
     */
    public function deactivate(): void
    {
        $session = Services::session();
        $session->remove([
            self::SESSION_ENTERED_AT,
            self::SESSION_EXPIRES_AT,
            self::SESSION_FACTOR,
        ]);
    }

    /**
     * Verify a submitted factor proof and automatically activate Sudo mode upon success.
     */
    public function verifyAndActivate(
        User $user,
        string $driverId,
        mixed $proof,
        int|string $lifetime = 7200,
        array $context = []
    ): bool {
        if (!$this->twoFactor->verify($user, $driverId, $proof, $context)) {
            return false;
        }

        $this->activate($lifetime, $driverId);

        return true;
    }

    /**
     * Timestamp when current Sudo mode was entered.
     */
    public function enteredAt(): ?int
    {
        $entered = Services::session()->get(self::SESSION_ENTERED_AT);
        return $entered ? (int) $entered : null;
    }

    /**
     * Timestamp when current Sudo mode will expire.
     */
    public function expiresAt(): ?int
    {
        $expires = Services::session()->get(self::SESSION_EXPIRES_AT);
        return $expires ? (int) $expires : null;
    }

    /**
     * Number of seconds remaining in active Sudo session.
     */
    public function secondsRemaining(): int
    {
        $expires = $this->expiresAt();
        if (!$expires) {
            return 0;
        }

        return max(0, $expires - time());
    }

    /**
     * Factor used to enter Sudo mode (e.g. 'passkey', 'totp', 'password').
     */
    public function factorUsed(): ?string
    {
        return Services::session()->get(self::SESSION_FACTOR);
    }

    /**
     * Parse lifetime string or integer into seconds.
     */
    public function parseLifetime(int|string $lifetime): int
    {
        if (is_numeric($lifetime)) {
            return (int) $lifetime;
        }

        $lifetime = trim(strtolower((string) $lifetime));

        if (preg_match('/^(\d+)\s*(s|sec|second|seconds)$/', $lifetime, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/^(\d+)\s*(m|min|minute|minutes)$/', $lifetime, $m)) {
            return (int) $m[1] * 60;
        }
        if (preg_match('/^(\d+)\s*(h|hr|hour|hours)$/', $lifetime, $m)) {
            return (int) $m[1] * 3600;
        }
        if (preg_match('/^(\d+)\s*(d|day|days)$/', $lifetime, $m)) {
            return (int) $m[1] * 86400;
        }

        $parsed = strtotime("+{$lifetime}", 0);
        return $parsed !== false && $parsed > 0 ? $parsed : 7200;
    }
}
