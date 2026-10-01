<?php

declare(strict_types=1);

namespace Jengo\Auth\TwoFactor;

use InvalidArgumentException;
use Jengo\Auth\Entities\User;
use Jengo\Auth\TwoFactor\Contracts\ChallengeableFactorInterface;
use Jengo\Auth\TwoFactor\Contracts\EnrollableFactorInterface;
use Jengo\Auth\TwoFactor\Contracts\FactorDriverInterface;
use Jengo\Auth\TwoFactor\Contracts\VerifiableFactorInterface;
use Jengo\Auth\TwoFactor\Drivers\EmailOtpDriver;
use Jengo\Auth\TwoFactor\Drivers\PasskeyDriver;
use Jengo\Auth\TwoFactor\Drivers\PasswordDriver;
use Jengo\Auth\TwoFactor\Drivers\RecoveryCodeDriver;
use Jengo\Auth\TwoFactor\Drivers\TotpDriver;

class TwoFactorManager
{
    /**
     * @var array<string, FactorDriverInterface>
     */
    protected array $drivers = [];

    public function __construct(array $customDrivers = [])
    {
        // Register default built-in drivers
        $this->registerDefaultDrivers();

        foreach ($customDrivers as $id => $driver) {
            $this->extend($id, $driver);
        }
    }

    /**
     * Register default first-party drivers.
     */
    protected function registerDefaultDrivers(): void
    {
        $this->drivers['passkey'] = new PasskeyDriver();
        $this->drivers['totp'] = new TotpDriver();
        $this->drivers['email_otp'] = new EmailOtpDriver();
        $this->drivers['recovery_code'] = new RecoveryCodeDriver();
        $this->drivers['password'] = new PasswordDriver();
    }

    /**
     * Register or override a factor driver.
     */
    public function extend(string $id, FactorDriverInterface|string $driver): self
    {
        $instance = is_string($driver) ? new $driver() : $driver;
        if (!$instance instanceof FactorDriverInterface) {
            throw new InvalidArgumentException("Factor driver [{$id}] must implement FactorDriverInterface.");
        }

        $this->drivers[$id] = $instance;

        return $this;
    }

    /**
     * Get a specific factor driver by ID.
     */
    public function driver(string $id): FactorDriverInterface
    {
        if (!isset($this->drivers[$id])) {
            throw new InvalidArgumentException("Unknown two-factor driver [{$id}].");
        }

        return $this->drivers[$id];
    }

    /**
     * Check if a driver is registered.
     */
    public function hasDriver(string $id): bool
    {
        return isset($this->drivers[$id]);
    }

    /**
     * Get all registered drivers.
     *
     * @return array<string, FactorDriverInterface>
     */
    public function drivers(): array
    {
        return $this->drivers;
    }

    /**
     * Return all enrolled drivers for a specific user.
     *
     * @return array<string, FactorDriverInterface>
     */
    public function enrolledDriversFor(User $user, array $allowedFactorIds = []): array
    {
        $enrolled = [];
        foreach ($this->drivers as $id => $driver) {
            if (!empty($allowedFactorIds) && !in_array($id, $allowedFactorIds, true)) {
                continue;
            }

            if ($driver->isEnrolled($user)) {
                $enrolled[$id] = $driver;
            }
        }

        return $enrolled;
    }

    /**
     * Get structured metadata for all enrolled factors for UI presentation.
     *
     * @return list<array{id: string, label: string, icon: string, description: string}>
     */
    public function getEnrolledFactorsSummary(User $user, array $allowedFactorIds = []): array
    {
        $summary = [];
        foreach ($this->enrolledDriversFor($user, $allowedFactorIds) as $id => $driver) {
            $summary[] = [
                'id'          => $driver->getId(),
                'label'       => $driver->getLabel(),
                'icon'        => $driver->getIcon(),
                'description' => $driver->getDescription(),
            ];
        }

        return $summary;
    }

    /**
     * Create a challenge for a user via a specific factor driver.
     *
     * @return array<string, mixed>
     */
    public function createChallenge(User $user, string $driverId, array $context = []): array
    {
        $driver = $this->driver($driverId);

        if ($driver instanceof ChallengeableFactorInterface) {
            return $driver->createChallenge($user, $context);
        }

        return [
            'type'        => $driver->getId(),
            'label'       => $driver->getLabel(),
            'description' => $driver->getDescription(),
        ];
    }

    /**
     * Verify submitted proof against a factor driver.
     */
    public function verify(User $user, string $driverId, mixed $proof, array $context = []): bool
    {
        $driver = $this->driver($driverId);

        if (!$driver instanceof VerifiableFactorInterface) {
            throw new InvalidArgumentException("Driver [{$driverId}] does not support proof verification.");
        }

        return $driver->verify($user, $proof, $context);
    }

    /**
     * Start enrollment for an enrollable driver.
     *
     * @return array<string, mixed>
     */
    public function startEnrollment(User $user, string $driverId, array $options = []): array
    {
        $driver = $this->driver($driverId);

        if (!$driver instanceof EnrollableFactorInterface) {
            throw new InvalidArgumentException("Driver [{$driverId}] does not support user enrollment.");
        }

        return $driver->startEnrollment($user, $options);
    }

    /**
     * Confirm enrollment for an enrollable driver.
     */
    public function confirmEnrollment(User $user, string $driverId, mixed $proof = null, array $metadata = []): bool
    {
        $driver = $this->driver($driverId);

        if (!$driver instanceof EnrollableFactorInterface) {
            throw new InvalidArgumentException("Driver [{$driverId}] does not support user enrollment.");
        }

        return $driver->confirmEnrollment($user, $proof, $metadata);
    }

    /**
     * Unenroll a factor driver for a user.
     */
    public function unenroll(User $user, string $driverId, ?string $credentialId = null): bool
    {
        $driver = $this->driver($driverId);

        if (!$driver instanceof EnrollableFactorInterface) {
            throw new InvalidArgumentException("Driver [{$driverId}] does not support unenrollment.");
        }

        return $driver->unenroll($user, $credentialId);
    }
}
