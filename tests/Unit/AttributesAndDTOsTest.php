<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Auth\Attributes\Authenticate;
use Jengo\Auth\Attributes\Can;
use Jengo\Auth\Attributes\Guest;
use Jengo\Auth\Attributes\Role;
use Jengo\Auth\Authentication\DTOs\AuthResult;
use Jengo\Auth\Authentication\DTOs\TokenResult;
use Jengo\Auth\DTOs\AuthResponseData;
use Jengo\Auth\Entities\User;
use Jengo\Auth\Entities\UserToken;
use PHPUnit\Framework\TestCase;

class AttributesAndDTOsTest extends TestCase
{
    public function testAttributeProperties(): void
    {
        $authAttr = new Authenticate('tokens');
        $this->assertSame('tokens', $authAttr->guard);

        $canAttr = new Can('posts.publish', 'posts', 'draft');
        $this->assertSame('posts.publish', $canAttr->permission);
        $this->assertSame('posts', $canAttr->resource);
        $this->assertSame('draft', $canAttr->schema);

        $roleAttr = new Role('superadmin', 'admin');
        $this->assertSame(['superadmin', 'admin'], $roleAttr->roles);
        

        $guestAttr = new Guest('/dashboard');
        $this->assertSame('/dashboard', $guestAttr->redirectTo);
    }

    public function testAuthResultAndTokenResultDTOs(): void
    {
        $user = new User(['username' => 'alice', 'email' => 'alice@example.com']);
        $user->id = 1;

        $authSuccess = AuthResult::success($user, ['role' => 'admin']);
        $this->assertTrue($authSuccess->isSuccess());
        $this->assertSame($user, $authSuccess->getUser());
        $this->assertSame(['role' => 'admin'], $authSuccess->extra);

        $authFailure = AuthResult::failure('Invalid credentials');
        $this->assertFalse($authFailure->isSuccess());
        $this->assertSame('Invalid credentials', $authFailure->getMessage());

        $tokenEntity = new UserToken([
            'name'       => 'API Token',
            'abilities'  => json_encode(['read', 'write']),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        $tokenResult = new TokenResult($tokenEntity, 'raw_token_xyz_123');
        $this->assertSame('raw_token_xyz_123', $tokenResult->plainTextToken);
        $this->assertSame($tokenEntity, $tokenResult->accessToken);

        $array = $tokenResult->toArray();
        $this->assertSame('Bearer', $array['token_type']);
        $this->assertSame('raw_token_xyz_123', $array['access_token']);
        $this->assertSame('API Token', $array['name']);
    }

    public function testAuthResponseDataDTO(): void
    {
        $user = new User(['username' => 'bob', 'email' => 'bob@example.com']);
        $user->id = 2;

        $data = new AuthResponseData(
            action: 'login',
            status: 'success',
            statusCode: 200,
            message: 'Authenticated successfully',
            data: ['token' => 'abc'],
            redirectTo: '/dashboard',
            user: $user
        );

        $this->assertSame('login', $data->action);
        $this->assertSame('success', $data->status);
        $this->assertSame(200, $data->statusCode);
        $this->assertSame('Authenticated successfully', $data->message);
        $this->assertSame('/dashboard', $data->redirectTo);
        $this->assertSame($user, $data->user);
    }
}
