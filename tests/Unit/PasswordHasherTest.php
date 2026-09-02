<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Auth\Authentication\Password\PasswordHasher;
use Tests\TestCase;

class PasswordHasherTest extends TestCase
{
    public function testHashAndVerify(): void
    {
        $hasher = new PasswordHasher();
        $password = 'secret-password-123';

        $hash = $hasher->hash($password);

        $this->assertNotEmpty($hash);
        $this->assertTrue($hasher->verify($password, $hash));
        $this->assertFalse($hasher->verify('wrong-password', $hash));
    }

    public function testVerifyEmptyPasswordReturnsFalse(): void
    {
        $hasher = new PasswordHasher();
        $this->assertFalse($hasher->verify('', 'some-hash'));
        $this->assertFalse($hasher->verify('password', ''));
    }
}
