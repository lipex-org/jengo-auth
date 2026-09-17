<?php

declare(strict_types=1);

namespace Tests\Unit;

use Config\Services;
use Jengo\Auth\Authentication\Authenticators\SessionGuard;
use Jengo\Auth\Authentication\Authenticators\TokenGuard;
use Jengo\Auth\Authentication\Authenticators\UniversalGuard;
use Jengo\Auth\Entities\User;
use Jengo\Auth\Entities\UserIdentity;
use Jengo\Auth\Models\UserIdentityModel;
use Jengo\Auth\Models\UserModel;
use Tests\TestCase;

class UniversalGuardTest extends TestCase
{
    protected UniversalGuard $guard;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = bin2hex(random_bytes(4));
        $userModel = new UserModel();
        $user = new User(['username' => 'universal_user_' . $suffix, 'active' => 1]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;
        $this->user = $user;

        $identityModel = new UserIdentityModel();
        $identity = new UserIdentity([
            'user_id' => $user->id,
            'type'    => 'email_password',
            'name'    => "universal_{$suffix}@example.com",
            'secret'  => auth()->getHasher()->hash('Password123!'),
        ]);
        $identityModel->insert($identity);

        $this->guard = new UniversalGuard();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION'], $_GET['api_token'], $_GET['token']);
        parent::tearDown();
    }

    public function testGettersReturnUnderlyingGuards(): void
    {
        $this->assertInstanceOf(TokenGuard::class, $this->guard->getTokenGuard());
        $this->assertInstanceOf(SessionGuard::class, $this->guard->getSessionGuard());
    }

    public function testAuthenticatesViaBearerHeader(): void
    {
        $tokenResult = auth()->createTokenFor($this->user, 'API Test Token', ['*']);
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$tokenResult->plainTextToken}";

        $this->assertTrue($this->guard->check());
        $this->assertFalse($this->guard->guest());
        $this->assertSame((int) $this->user->id, $this->guard->id());
        $this->assertSame($this->user->username, $this->guard->user()?->username);

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testAuthenticatesViaApiTokenQueryParam(): void
    {
        $tokenResult = auth()->createTokenFor($this->user, 'Query Token', ['*']);
        $_GET['api_token'] = $tokenResult->plainTextToken;

        $this->assertTrue($this->guard->check());
        $this->assertSame((int) $this->user->id, $this->guard->id());

        unset($_GET['api_token']);
    }

    public function testAuthenticatesViaTokenQueryParam(): void
    {
        $tokenResult = auth()->createTokenFor($this->user, 'Token Param', ['*']);
        $_GET['token'] = $tokenResult->plainTextToken;

        $this->assertTrue($this->guard->check());
        $this->assertSame((int) $this->user->id, $this->guard->id());

        unset($_GET['token']);
    }

    public function testFallsBackToSessionGuard(): void
    {
        // No tokens present
        unset($_SERVER['HTTP_AUTHORIZATION'], $_GET['api_token'], $_GET['token']);

        $this->assertNull($this->guard->user());
        $this->assertTrue($this->guard->guest());

        // Log in on session
        $this->guard->login($this->user);

        $this->assertTrue($this->guard->check());
        $this->assertSame((int) $this->user->id, $this->guard->id());
    }

    public function testAttemptDelegatesToSessionGuard(): void
    {
        $result = $this->guard->attempt([
            'email'    => $this->user->getEmail(),
            'password' => 'Password123!',
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($this->guard->check());
    }

    public function testLogoutClearsBothGuards(): void
    {
        $tokenResult = auth()->createTokenFor($this->user, 'Logout Test', ['*']);
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$tokenResult->plainTextToken}";

        // Authenticate
        $this->assertTrue($this->guard->check());

        // Logout
        $this->guard->logout();

        unset($_SERVER['HTTP_AUTHORIZATION']);
        $this->assertNull($this->guard->user());
        $this->assertTrue($this->guard->guest());
    }

    public function testSetUserSetsUserOnBothGuards(): void
    {
        $newUser = new User(['id' => 999, 'username' => 'swapped_user', 'active' => 1]);
        $this->guard->setUser($newUser);

        $this->assertSame(999, $this->guard->getSessionGuard()->id());
        $this->assertSame(999, $this->guard->getTokenGuard()->id());
    }
}
