<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTime;
use Jengo\Auth\Entities\User;
use Jengo\Auth\Models\UserModel;
use Tests\TestCase;

class TokenGuardTest extends TestCase
{
    public function testTokenCreationAndVerification(): void
    {
        $userModel = new UserModel();
        $user = new User([
            'username' => 'tokenuser',
            'active'   => 1,
        ]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $tokenResult = auth()->createTokenFor($user, 'Test Token', ['posts.read', 'posts.create']);

        $this->assertNotEmpty($tokenResult->plainTextToken);
        $this->assertSame('Test Token', $tokenResult->accessToken->name);

        // Test with Authorization header
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$tokenResult->plainTextToken}";

        $auth = auth();
        $tokenGuard = $auth->guard('token');

        $this->assertTrue($tokenGuard->check());
        $this->assertSame((int) $user->id, (int) $tokenGuard->id());

        $token = $tokenGuard->currentToken();
        $this->assertNotNull($token);
        $this->assertTrue($token->can('posts.read'));
        $this->assertTrue($token->can('posts.create'));
        $this->assertFalse($token->can('posts.delete'));

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testExpiredTokenIsRejected(): void
    {
        $userModel = new UserModel();
        $user = new User([
            'username' => 'expireduser',
            'active'   => 1,
        ]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $yesterday = new DateTime('-1 day');
        $tokenResult = auth()->createTokenFor($user, 'Expired Token', ['*'], $yesterday);

        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$tokenResult->plainTextToken}";

        $auth = auth();
        $this->assertFalse($auth->guard('token')->check());
        $this->assertNull($auth->guard('token')->user());

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }
}
