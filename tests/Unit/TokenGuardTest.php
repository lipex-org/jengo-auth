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

    public function testTokenAuthenticationViaQueryParameter(): void
    {
        $userModel = new UserModel();
        $user = new User([
            'username' => 'query_token_user_' . bin2hex(random_bytes(4)),
            'active'   => 1,
        ]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $tokenResult = auth()->createTokenFor($user, 'Query Token', ['reports.view']);

        // Test with ?token=
        $_GET['token'] = $tokenResult->plainTextToken;
        \Config\Services::resetSingle('request');

        $auth = auth();
        $guard = $auth->guard('token');

        $this->assertTrue($guard->check());
        $this->assertSame((int) $user->id, (int) $guard->id());
        $this->assertTrue($guard->currentToken()->can('reports.view'));

        unset($_GET['token']);
        \Config\Services::resetSingle('request');

        // Test with ?api_token=
        $_GET['api_token'] = $tokenResult->plainTextToken;
        \Config\Services::resetSingle('request');

        $guard = new \Jengo\Auth\Authentication\Authenticators\TokenGuard();
        $this->assertTrue($guard->check());
        $this->assertSame((int) $user->id, (int) $guard->id());

        unset($_GET['api_token']);
        \Config\Services::resetSingle('request');
    }

    public function testBannedUserTokenIsRejected(): void
    {
        $userModel = new UserModel();
        $user = new User([
            'username' => 'banned_tok_' . bin2hex(random_bytes(4)),
            'active'   => 1,
            'status'   => 'banned',
        ]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $tokenResult = auth()->createTokenFor($user, 'Banned Token', ['*']);

        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$tokenResult->plainTextToken}";
        \Config\Services::resetSingle('request');

        $guard = new \Jengo\Auth\Authentication\Authenticators\TokenGuard();
        $this->assertFalse($guard->check());
        $this->assertNull($guard->user());

        unset($_SERVER['HTTP_AUTHORIZATION']);
        \Config\Services::resetSingle('request');
    }

    public function testInactiveUserTokenIsRejected(): void
    {
        $userModel = new UserModel();
        $user = new User([
            'username' => 'inactive_tok_' . bin2hex(random_bytes(4)),
            'active'   => 0,
        ]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $tokenResult = auth()->createTokenFor($user, 'Inactive Token', ['*']);

        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$tokenResult->plainTextToken}";
        \Config\Services::resetSingle('request');

        $guard = new \Jengo\Auth\Authentication\Authenticators\TokenGuard();
        $this->assertFalse($guard->check());
        $this->assertNull($guard->user());

        unset($_SERVER['HTTP_AUTHORIZATION']);
        \Config\Services::resetSingle('request');
    }

    public function testTokenLogoutRevokesCurrentToken(): void
    {
        $userModel = new UserModel();
        $user = new User([
            'username' => 'logout_tok_' . bin2hex(random_bytes(4)),
            'active'   => 1,
        ]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $tokenResult = auth()->createTokenFor($user, 'Revocable Token', ['*']);

        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$tokenResult->plainTextToken}";
        \Config\Services::resetSingle('request');

        $guard = new \Jengo\Auth\Authentication\Authenticators\TokenGuard();
        $this->assertTrue($guard->check());

        // Logout deletes the token
        $guard->logout();
        $this->assertNull($guard->user());

        // Verify token no longer works
        $guard2 = new \Jengo\Auth\Authentication\Authenticators\TokenGuard();
        $this->assertFalse($guard2->check());

        unset($_SERVER['HTTP_AUTHORIZATION']);
        \Config\Services::resetSingle('request');
    }

    public function testRevokeAllTokensForUser(): void
    {
        $userModel = new UserModel();
        $user = new User([
            'username' => 'revoke_all_' . bin2hex(random_bytes(4)),
            'active'   => 1,
        ]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $tok1 = auth()->createTokenFor($user, 'Tok 1');
        $tok2 = auth()->createTokenFor($user, 'Tok 2');

        $tokenModel = new \Jengo\Auth\Models\UserTokenModel();
        $this->assertCount(2, $tokenModel->where('user_id', $user->id)->findAll());

        $guard = new \Jengo\Auth\Authentication\Authenticators\TokenGuard();
        $revoked = $guard->revokeAllTokens($user);
        $this->assertTrue($revoked);

        $this->assertCount(0, $tokenModel->where('user_id', $user->id)->findAll());
    }

    public function testRepeatedTokenChecksInSameSecondDoNotThrow(): void
    {
        $userModel = new UserModel();
        $user = new User([
            'username' => 'repeat_check_' . bin2hex(random_bytes(4)),
            'active'   => 1,
        ]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $tokenResult = auth()->createTokenFor($user, 'Repeat Check Token');

        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$tokenResult->plainTextToken}";
        \Config\Services::resetSingle('request');

        // First check
        $guard1 = new \Jengo\Auth\Authentication\Authenticators\TokenGuard();
        $this->assertTrue($guard1->check());

        // Immediate second check in new guard instance (same second)
        $guard2 = new \Jengo\Auth\Authentication\Authenticators\TokenGuard();
        $this->assertTrue($guard2->check());

        unset($_SERVER['HTTP_AUTHORIZATION']);
        \Config\Services::resetSingle('request');
    }
}
