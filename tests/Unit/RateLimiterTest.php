<?php

declare(strict_types=1);

namespace Tests\Unit;

use Config\Services;
use Jengo\Auth\Authentication\Throttling\RateLimiter;
use Jengo\Auth\Entities\User;
use Tests\TestCase;

class RateLimiterTest extends TestCase
{
    public function testAuthenticatedUserThrottlingUsesUserId(): void
    {
        $limiter = new RateLimiter();
        $user = new User(['id' => 42, 'username' => 'alice']);

        $key = $limiter->forUser($user, 'api_requests');
        $this->assertSame('throttle:user:42:api_requests', $key);
    }

    public function testGuestThrottlingIsolatesDifferentAccountsOnSameIp(): void
    {
        $limiter = new RateLimiter();
        $request = Services::request();

        $keyAlice = $limiter->forGuest($request, 'alice@example.com', 'login');
        $keyBob   = $limiter->forGuest($request, 'bob@example.com', 'login');

        // Ensure alice and bob have distinct throttle keys even from the exact same IP
        $this->assertNotSame($keyAlice, $keyBob);
        $this->assertStringContainsString('alice@example.com', $keyAlice);
        $this->assertStringContainsString('bob@example.com', $keyBob);
    }

    public function testGuestThrottlingIsolatesDifferentDevicesOnSameIp(): void
    {
        $limiter = new RateLimiter();

        $req1 = clone Services::request();
        $req1->setHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        $key1 = $limiter->forGuest($req1, 'sameuser@example.com', 'login');

        $req2 = clone Services::request();
        $req2->setHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)');
        $key2 = $limiter->forGuest($req2, 'sameuser@example.com', 'login');

        // Different user agents on the same IP yield distinct keys
        $this->assertNotSame($key1, $key2);
    }

    public function testHitAndTooManyAttemptsAndClear(): void
    {
        $limiter = new RateLimiter();
        $key = 'test:rate:limit:' . uniqid();

        $this->assertFalse($limiter->tooManyAttempts($key, 3));

        // Consume 3 attempts
        $limiter->hit($key, 60);
        $limiter->hit($key, 60);
        $limiter->hit($key, 60);

        $this->assertTrue($limiter->tooManyAttempts($key, 3));

        $limiter->clear($key);
        $this->assertFalse($limiter->tooManyAttempts($key, 3));
    }

    public function testIpKeyNormalization(): void
    {
        $limiter = new RateLimiter();

        $req1 = clone Services::request();
        // default request has empty or localhost IP
        $key1 = $limiter->ipKey($req1, 'login');
        $this->assertSame('throttle:ip:login:localhost', $key1);
    }

    public function testAccountKeyNormalization(): void
    {
        $limiter = new RateLimiter();

        $key1 = $limiter->accountKey('  User@Domain.COM  ', 'login');
        $key2 = $limiter->accountKey('user@domain.com', 'login');
        $this->assertSame('throttle:account:login:user@domain.com', $key1);
        $this->assertSame($key1, $key2);

        $emptyKey = $limiter->accountKey(null, 'login');
        $this->assertSame('throttle:account:login:global', $emptyKey);
    }

    public function testAvailableInReturnsRemainingSeconds(): void
    {
        $limiter = new RateLimiter();
        $key = 'test:avail:' . uniqid();

        // Before any hit, returns default 60
        $this->assertSame(60, $limiter->availableIn($key));

        // After hitting with 120s decay
        $limiter->hit($key, 120);
        $remaining = $limiter->availableIn($key);
        $this->assertGreaterThan(0, $remaining);
        $this->assertLessThanOrEqual(120, $remaining);

        $limiter->clear($key);
        $this->assertSame(60, $limiter->availableIn($key));
    }

    public function testResolveKeyUsesAuthenticatedUserOrGuest(): void
    {
        $limiter = new RateLimiter();
        $request = Services::request();

        // 1. Guest
        $guestKey = $limiter->resolveKey($request, 'guest@test.com', 'api');
        $this->assertStringContainsString('throttle:guest:api:guest@test.com:', $guestKey);

        // 2. Authenticated user
        $user = new User(['id' => 99, 'username' => 'auth_tester']);
        auth()->login($user);

        $authKey = $limiter->resolveKey($request, 'guest@test.com', 'api');
        $this->assertSame('throttle:user:99:api', $authKey);

        auth()->logout();
    }

    public function testIsThrottledAndRecordFailureAndRecordSuccess(): void
    {
        $limiter = new RateLimiter();
        $request = Services::request();
        $identifier = 'target_' . uniqid() . '@example.com';

        $this->assertFalse($limiter->isThrottled($request, $identifier, 'login', 3));

        // Record 3 failures
        $limiter->recordFailure($request, $identifier, 'login');
        $limiter->recordFailure($request, $identifier, 'login');
        $limiter->recordFailure($request, $identifier, 'login');

        $this->assertTrue($limiter->isThrottled($request, $identifier, 'login', 3));

        // Record success clears both buckets
        $limiter->recordSuccess($request, $identifier, 'login');
        $this->assertFalse($limiter->isThrottled($request, $identifier, 'login', 3));
    }
}
