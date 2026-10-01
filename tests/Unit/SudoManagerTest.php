<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use Jengo\Auth\Entities\User;
use Jengo\Auth\Sudo\SudoManager;
use Jengo\Auth\TwoFactor\TwoFactorManager;

final class SudoManagerTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Services::session()->destroy();
    }

    public function testSudoActivationAndCheck(): void
    {
        $manager = new SudoManager(new TwoFactorManager());

        $this->assertFalse($manager->check());
        $this->assertSame(0, $manager->secondsRemaining());

        // Activate with 2 hours (7200 seconds)
        $manager->activate(7200, 'password');

        $this->assertTrue($manager->check());
        $this->assertGreaterThan(7100, $manager->secondsRemaining());
        $this->assertSame('password', $manager->factorUsed());

        // Deactivate
        $manager->deactivate();
        $this->assertFalse($manager->check());
    }

    public function testSudoParseLifetimeStrings(): void
    {
        $manager = new SudoManager(new TwoFactorManager());

        $this->assertSame(300, $manager->parseLifetime('5 minutes'));
        $this->assertSame(3600, $manager->parseLifetime('1 hour'));
        $this->assertSame(7200, $manager->parseLifetime('2 hours'));
        $this->assertSame(86400, $manager->parseLifetime('1 day'));
        $this->assertSame(1800, $manager->parseLifetime(1800));
    }

    public function testVerifyAndActivateWithPassword(): void
    {
        $user = new User([
            'id'            => 1,
            'password_hash' => password_hash('mypassword', PASSWORD_BCRYPT),
        ]);

        $manager = new SudoManager(new TwoFactorManager());

        $this->assertFalse($manager->check());

        // Wrong password
        $this->assertFalse($manager->verifyAndActivate($user, 'password', 'wrong'));
        $this->assertFalse($manager->check());

        // Correct password
        $this->assertTrue($manager->verifyAndActivate($user, 'password', 'mypassword', '15 minutes'));
        $this->assertTrue($manager->check());
        $this->assertSame('password', $manager->factorUsed());
    }
}
