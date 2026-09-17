<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Auth\Entities\User;
use Jengo\Auth\Entities\UserIdentity;
use Jengo\Auth\Models\UserIdentityModel;
use Jengo\Auth\Models\UserModel;
use Tests\TestCase;

class SessionGuardTest extends TestCase
{
    public function testSessionGuardLoginAndCheck(): void
    {
        $userModel = new UserModel();
        $user = new User([
            'username' => 'johndoe',
            'active'   => 1,
        ]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $auth = auth();
        $this->assertTrue($auth->guard('session')->guest());

        $auth->guard('session')->login($user);

        $this->assertTrue($auth->guard('session')->check());
        $this->assertSame((int) $user->id, $auth->guard('session')->id());

        $auth->guard('session')->logout();
        $this->assertTrue($auth->guard('session')->guest());
    }

    public function testSessionGuardAttemptWithValidCredentials(): void
    {
        $userModel = new UserModel();
        $user = new User([
            'username' => 'janedoe',
            'active'   => 1,
        ]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $hasher = auth()->getHasher();
        $identityModel = new UserIdentityModel();
        $identity = new UserIdentity([
            'user_id' => $user->id,
            'type'    => 'email_password',
            'name'    => 'jane@example.com',
            'secret'  => $hasher->hash('secret123'),
        ]);
        $identityModel->insert($identity);

        $auth = auth();
        $result = $auth->guard('session')->attempt([
            'email'    => 'jane@example.com',
            'password' => 'secret123',
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertNotNull($result->getUser());
        $this->assertSame((int) $user->id, (int) $result->getUser()->id);
        $this->assertTrue($auth->guard('session')->check());
    }

    public function testSessionGuardAttemptWithInvalidPassword(): void
    {
        $userModel = new UserModel();
        $user = new User([
            'username' => 'alex',
            'active'   => 1,
        ]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $hasher = auth()->getHasher();
        $identityModel = new UserIdentityModel();
        $identity = new UserIdentity([
            'user_id' => $user->id,
            'type'    => 'email_password',
            'name'    => 'alex@example.com',
            'secret'  => $hasher->hash('correct-password'),
        ]);
        $identityModel->insert($identity);

        $auth = auth();
        $result = $auth->guard('session')->attempt([
            'email'    => 'alex@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($auth->guard('session')->guest());
    }

    public function testSessionGuardAttemptFailsForBannedUser(): void
    {
        $userModel = new UserModel();
        $user = new User([
            'username' => 'banned_' . bin2hex(random_bytes(4)),
            'active'   => 1,
            'status'   => 'banned',
        ]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $hasher = auth()->getHasher();
        $identityModel = new UserIdentityModel();
        $identity = new UserIdentity([
            'user_id' => $user->id,
            'type'    => 'email_password',
            'name'    => 'banned@example.com',
            'secret'  => $hasher->hash('secret123'),
        ]);
        $identityModel->insert($identity);

        $auth = auth();
        $result = $auth->guard('session')->attempt([
            'email'    => 'banned@example.com',
            'password' => 'secret123',
        ]);

        $this->assertFalse($result->isSuccess());
        $this->assertSame('User account is inactive or banned.', $result->getMessage());
        $this->assertTrue($auth->guard('session')->guest());
    }

    public function testSessionGuardAttemptFailsForInactiveUser(): void
    {
        $userModel = new UserModel();
        $user = new User([
            'username' => 'inactive_' . bin2hex(random_bytes(4)),
            'active'   => 0,
        ]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $hasher = auth()->getHasher();
        $identityModel = new UserIdentityModel();
        $identity = new UserIdentity([
            'user_id' => $user->id,
            'type'    => 'email_password',
            'name'    => 'inactive@example.com',
            'secret'  => $hasher->hash('secret123'),
        ]);
        $identityModel->insert($identity);

        $auth = auth();
        $result = $auth->guard('session')->attempt([
            'email'    => 'inactive@example.com',
            'password' => 'secret123',
        ]);

        $this->assertFalse($result->isSuccess());
        $this->assertSame('User account is inactive or banned.', $result->getMessage());
        $this->assertTrue($auth->guard('session')->guest());
    }

    public function testSessionGuardAttemptFailsWhenMissingCredentials(): void
    {
        $auth = auth();

        $result1 = $auth->guard('session')->attempt([]);
        $this->assertFalse($result1->isSuccess());
        $this->assertSame('Email/Username and Password are required.', $result1->getMessage());

        $result2 = $auth->guard('session')->attempt(['email' => 'missing@example.com']);
        $this->assertFalse($result2->isSuccess());
        $this->assertSame('Email/Username and Password are required.', $result2->getMessage());

        $result3 = $auth->guard('session')->attempt(['email' => 'ghost@example.com', 'password' => 'pwd']);
        $this->assertFalse($result3->isSuccess());
        $this->assertSame('Invalid credentials.', $result3->getMessage());
    }

    public function testSessionGuardRememberMeLifecycleAndLogoutRevocation(): void
    {
        $userModel = new UserModel();
        $user = new User([
            'username' => 'remember_' . bin2hex(random_bytes(4)),
            'active'   => 1,
        ]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $hasher = auth()->getHasher();
        $identityModel = new UserIdentityModel();
        $identity = new UserIdentity([
            'user_id' => $user->id,
            'type'    => 'email_password',
            'name'    => 'remember@example.com',
            'secret'  => $hasher->hash('secret123'),
        ]);
        $identityModel->insert($identity);

        $guard = auth()->guard('session');

        // Login with remember = true
        $result = $guard->attempt([
            'email'    => 'remember@example.com',
            'password' => 'secret123',
        ], true);

        $this->assertTrue($result->isSuccess());

        // Verify remember_token identity created in database
        $rememberIdentity = $identityModel
            ->where('user_id', $user->id)
            ->where('type', 'remember_token')
            ->first();

        $this->assertNotNull($rememberIdentity);
        $this->assertNotEmpty($rememberIdentity->name);
        $this->assertNotEmpty($rememberIdentity->secret);

        // Verify cookie was set on response
        $response = \Config\Services::response();
        $cookie = $response->getCookie('remember_token');
        $this->assertNotNull($cookie);
        $this->assertSame('/', $cookie->getPath());

        // Simulate session expiry: reset guard and session
        \Config\Services::session()->remove('auth_user_id');
        $guard->logout(); // This also revokes the remember_token identity in DB

        $revokedIdentity = $identityModel
            ->where('user_id', $user->id)
            ->where('type', 'remember_token')
            ->first();
        $this->assertNull($revokedIdentity);
    }

    public function testSessionGuardRecallUserFromCookie(): void
    {
        $userModel = new UserModel();
        $user = new User([
            'username' => 'recall_' . bin2hex(random_bytes(4)),
            'active'   => 1,
        ]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $identityModel = new UserIdentityModel();
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));

        $identity = new UserIdentity([
            'user_id' => $user->id,
            'type'    => 'remember_token',
            'name'    => $selector,
            'secret'  => hash('sha256', $validator),
            'expires' => date('Y-m-d H:i:s', time() + 3600),
        ]);
        $identityModel->insert($identity);

        // Populate $_COOKIE
        $_COOKIE['remember_token'] = "{$selector}:{$validator}";
        \Config\Services::resetSingle('request');

        $guard = auth()->guard('session');
        $recalled = $guard->user();

        $this->assertNotNull($recalled);
        $this->assertSame($user->id, $recalled->id);
        $this->assertTrue($guard->check());

        // Cleanup
        unset($_COOKIE['remember_token']);
        \Config\Services::resetSingle('request');
        $guard->logout();
    }
}
