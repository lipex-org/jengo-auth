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
}
