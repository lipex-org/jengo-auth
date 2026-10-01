<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Auth\Entities\User;
use Jengo\Auth\TwoFactor\Contracts\FactorDriverInterface;
use Jengo\Auth\TwoFactor\Contracts\VerifiableFactorInterface;
use Jengo\Auth\TwoFactor\TwoFactorManager;

final class TwoFactorManagerTest extends CIUnitTestCase
{
    public function testDefaultDriversRegistration(): void
    {
        $manager = new TwoFactorManager();

        $this->assertTrue($manager->hasDriver('passkey'));
        $this->assertTrue($manager->hasDriver('totp'));
        $this->assertTrue($manager->hasDriver('email_otp'));
        $this->assertTrue($manager->hasDriver('recovery_code'));
        $this->assertTrue($manager->hasDriver('password'));
    }

    public function testExtendWithCustomDriver(): void
    {
        $customDriver = new class implements FactorDriverInterface, VerifiableFactorInterface {
            public function getId(): string { return 'duo'; }
            public function getLabel(): string { return 'Duo Push'; }
            public function getIcon(): string { return 'shield'; }
            public function getDescription(): string { return 'Duo Mobile prompt'; }
            public function isEnrolled(User $user): bool { return true; }
            public function verify(User $user, mixed $proof, array $context = []): bool {
                return $proof === 'duo_approved';
            }
        };

        $manager = new TwoFactorManager();
        $manager->extend('duo', $customDriver);

        $this->assertTrue($manager->hasDriver('duo'));
        $this->assertSame('Duo Push', $manager->driver('duo')->getLabel());

        $user = new User(['id' => 1]);
        $this->assertTrue($manager->verify($user, 'duo', 'duo_approved'));
        $this->assertFalse($manager->verify($user, 'duo', 'duo_denied'));
    }

    public function testEnrolledDriversSummary(): void
    {
        $manager = new TwoFactorManager();
        $user = new User(['id' => 1, 'email' => 'user@example.com', 'password_hash' => password_hash('secret123', PASSWORD_BCRYPT)]);

        $summary = $manager->getEnrolledFactorsSummary($user);
        $ids = array_column($summary, 'id');

        $this->assertContains('password', $ids);
        $this->assertContains('email_otp', $ids);
    }
}
