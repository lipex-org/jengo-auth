<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTime;
use Jengo\Auth\Entities\UserToken;
use PHPUnit\Framework\TestCase;

class UserTokenTest extends TestCase
{
    public function testTokenCanWithWildcardAll(): void
    {
        $token = new UserToken([
            'abilities' => ['*'],
        ]);

        $this->assertTrue($token->can('posts.create'));
        $this->assertTrue($token->can('users.delete'));
        $this->assertFalse($token->cannot('posts.create'));
    }

    public function testTokenCanWithExactMatch(): void
    {
        $token = new UserToken([
            'abilities' => ['posts.read', 'posts.update'],
        ]);

        $this->assertTrue($token->can('posts.read'));
        $this->assertTrue($token->can('posts.update'));
        $this->assertFalse($token->can('posts.delete'));
        $this->assertTrue($token->cannot('posts.delete'));
    }

    public function testTokenCanWithPrefixWildcard(): void
    {
        $token = new UserToken([
            'abilities' => ['posts.*', 'reports.view'],
        ]);

        $this->assertTrue($token->can('posts.create'));
        $this->assertTrue($token->can('posts.read'));
        $this->assertTrue($token->can('posts.delete'));
        $this->assertTrue($token->can('reports.view'));

        $this->assertFalse($token->can('reports.delete'));
        $this->assertTrue($token->cannot('reports.delete'));
        $this->assertFalse($token->can('users.create'));
    }

    public function testTokenIsExpiredWhenExpiresAtIsNull(): void
    {
        $token = new UserToken([
            'expires_at' => null,
        ]);

        $this->assertFalse($token->isExpired());
    }

    public function testTokenIsExpiredWhenInPast(): void
    {
        $token = new UserToken([
            'expires_at' => new DateTime('-1 hour'),
        ]);

        $this->assertTrue($token->isExpired());
    }

    public function testTokenIsNotExpiredWhenInFuture(): void
    {
        $token = new UserToken([
            'expires_at' => new DateTime('+1 day'),
        ]);

        $this->assertFalse($token->isExpired());
    }
}
