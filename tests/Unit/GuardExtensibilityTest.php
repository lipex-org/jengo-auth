<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Auth\Authentication\Authenticators\SessionGuard;
use Jengo\Auth\Authentication\Authenticators\TokenGuard;
use Jengo\Auth\Authentication\Authenticators\UniversalGuard;
use Jengo\Auth\Authentication\Contracts\GuardInterface;
use Jengo\Auth\Authentication\DTOs\AuthResult;
use Jengo\Auth\Entities\User;
use Tests\TestCase;

class MockCustomGuard implements GuardInterface
{
    public ?User $user = null;

    public function check(): bool
    {
        return $this->user !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function user(): ?User
    {
        return $this->user;
    }

    public function id(): ?int
    {
        return $this->user?->id;
    }

    public function attempt(array $credentials = [], bool $remember = false): AuthResult
    {
        if (($credentials['token'] ?? '') === 'valid-secret') {
            $this->user = new User(['id' => 999, 'username' => 'customuser']);
            return AuthResult::success($this->user);
        }

        return AuthResult::failure('Invalid secret');
    }

    public function login(User $user, bool $remember = false): void
    {
        $this->user = $user;
    }

    public function logout(): void
    {
        $this->user = null;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }
}

class GuardExtensibilityTest extends TestCase
{
    public function testResolvesBuiltInGuardsAndAliases(): void
    {
        $auth = auth();

        $this->assertInstanceOf(UniversalGuard::class, $auth->guard('universal'));
        $this->assertInstanceOf(SessionGuard::class, $auth->guard('session'));
        $this->assertInstanceOf(SessionGuard::class, $auth->guard('web'));
        $this->assertInstanceOf(TokenGuard::class, $auth->guard('token'));
        $this->assertInstanceOf(TokenGuard::class, $auth->guard('api'));
        $this->assertInstanceOf(TokenGuard::class, $auth->guard('bearer'));
    }

    public function testExtendingWithCustomGuardClosure(): void
    {
        $auth = auth();

        $auth->extend('custom', static function () {
            return new MockCustomGuard();
        });

        $guard = $auth->guard('custom');
        $this->assertInstanceOf(MockCustomGuard::class, $guard);
        $this->assertFalse($guard->check());

        $result = $guard->attempt(['token' => 'valid-secret']);
        $this->assertTrue($result->isSuccess());
        $this->assertTrue($guard->check());
        $this->assertSame(999, $guard->id());
    }

    public function testSettingGuardInstanceDirectly(): void
    {
        $auth = auth();
        $customGuard = new MockCustomGuard();
        $customGuard->user = new User(['id' => 777, 'username' => 'direct']);

        $auth->setGuard('direct', $customGuard);

        $this->assertTrue($auth->guard('direct')->check());
        $this->assertSame(777, $auth->guard('direct')->id());
    }

    public function testConfigRegisteredCustomGuard(): void
    {
        config('Auth')->guards['custom_config'] = MockCustomGuard::class;

        $auth = auth();
        $guard = $auth->guard('custom_config');

        $this->assertInstanceOf(MockCustomGuard::class, $guard);
    }

    public function testChangingDefaultDriver(): void
    {
        $auth = auth();
        $customGuard = new MockCustomGuard();
        $customGuard->user = new User(['id' => 123, 'username' => 'default_user']);

        $auth->setGuard('custom_default', $customGuard);
        $auth->setDefaultDriver('custom_default');

        $this->assertSame('custom_default', $auth->getDefaultDriver());
        $this->assertTrue($auth->check());
        $this->assertSame('default_user', $auth->user()->username);
    }
}
