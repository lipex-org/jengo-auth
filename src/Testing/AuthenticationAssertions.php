<?php

declare(strict_types=1);

namespace Jengo\Auth\Testing;

use Jengo\Auth\Config\Services;
use Jengo\Auth\Entities\User;
use PHPUnit\Framework\Assert;

trait AuthenticationAssertions
{
    /**
     * Authenticate as the given user for the test.
     */
    public function actingAs(User $user, ?string $guard = null): self
    {
        $auth = Services::auth();
        if ($guard) {
            $auth->guard($guard)->setUser($user);
        } else {
            $auth->login($user);
        }

        return $this;
    }

    /**
     * Attach a Bearer token to requests.
     */
    public function withToken(string $token): self
    {
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$token}";
        return $this;
    }

    /**
     * Assert that the user is authenticated.
     */
    public function assertAuthenticated(?string $guard = null): self
    {
        $auth = Services::auth();
        $isChecked = $guard ? $auth->guard($guard)->check() : $auth->check();

        Assert::assertTrue($isChecked, 'Expected user to be authenticated, but user is guest.');
        return $this;
    }

    /**
     * Assert that the user is a guest.
     */
    public function assertGuest(?string $guard = null): self
    {
        $auth = Services::auth();
        $isGuest = $guard ? $auth->guard($guard)->guest() : $auth->guest();

        Assert::assertTrue($isGuest, 'Expected guest, but user is authenticated.');
        return $this;
    }

    /**
     * Assert authenticated as specific user.
     */
    public function assertAuthenticatedAs(User $user, ?string $guard = null): self
    {
        $this->assertAuthenticated($guard);
        $auth = Services::auth();
        $current = $guard ? $auth->guard($guard)->user() : $auth->user();

        Assert::assertNotNull($current);
        Assert::assertSame((int) $user->id, (int) $current->id, "Authenticated user ID does not match expected user ID.");
        return $this;
    }

    /**
     * Assert user can perform action.
     */
    public function assertCan(string $permission, mixed $resource = null, ?User $user = null): self
    {
        $can = Services::auth()->can($permission, $resource, $user);
        Assert::assertTrue($can, "Expected user to have permission [{$permission}], but access was denied.");
        return $this;
    }

    /**
     * Assert user cannot perform action.
     */
    public function assertCannot(string $permission, mixed $resource = null, ?User $user = null): self
    {
        $can = Services::auth()->can($permission, $resource, $user);
        Assert::assertFalse($can, "Expected user NOT to have permission [{$permission}], but access was granted.");
        return $this;
    }

    /**
     * Assert user has role.
     */
    public function assertUserHasRole(User $user, string $role): self
    {
        $has = Services::auth()->hasRole($user, $role);
        Assert::assertTrue($has, "Expected user to have role [{$role}], but role was not found.");
        return $this;
    }
}
