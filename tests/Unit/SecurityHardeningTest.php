<?php

declare(strict_types=1);

namespace Tests\Unit;

use Config\Services;
use Jengo\Auth\Authentication\Throttling\RateLimiter;
use Jengo\Auth\Entities\User;
use Jengo\Auth\Entities\UserIdentity;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    public function testEmailCanonicalizationOnLookup(): void
    {
        $auth = auth();

        // Create user with unique lowercase email and username
        $user = new User(['username' => 'canonical_user_test', 'active' => 1, 'status' => 'active']);
        $userId = $auth->getUserModel()->insert($user);

        $auth->getUserIdentityModel()->insert(new UserIdentity([
            'user_id' => (int) $userId,
            'type'    => 'email_password',
            'name'    => 'canonical_test@example.com',
            'secret'  => $auth->getHasher()->hash('Secret123!'),
        ]));

        // Lookup with uppercase and leading/trailing whitespace
        $found1 = $auth->getUserModel()->findByIdentifier('  Canonical_Test@Example.COM  ');
        $this->assertNotNull($found1);
        $this->assertSame((int) $userId, (int) $found1->id);

        $found2 = $auth->getUserModel()->findByIdentifier('  CANONICAL_USER_TEST  ');
        $this->assertNotNull($found2);
        $this->assertSame((int) $userId, (int) $found2->id);
    }

    public function testDualBucketRateLimiterAccountLockout(): void
    {
        $rateLimiter = new RateLimiter();
        $request = Services::request();

        $identifier = 'unique_victim@example.com';
        $maxAttempts = 3;
        $decay = 60;

        // Reset cache for clean test
        $rateLimiter->recordSuccess($request, $identifier, 'login');

        $this->assertFalse($rateLimiter->isThrottled($request, $identifier, 'login', $maxAttempts, $decay));

        // Simulate 3 failures
        for ($i = 0; $i < $maxAttempts; $i++) {
            $rateLimiter->recordFailure($request, $identifier, 'login', $decay);
        }

        $this->assertTrue($rateLimiter->isThrottled($request, $identifier, 'login', $maxAttempts, $decay));

        // Clear and verify
        $rateLimiter->recordSuccess($request, $identifier, 'login');
        $this->assertFalse($rateLimiter->isThrottled($request, $identifier, 'login', $maxAttempts, $decay));
    }
}
