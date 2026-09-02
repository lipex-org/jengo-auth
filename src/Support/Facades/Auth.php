<?php

declare(strict_types=1);

namespace Jengo\Auth\Support\Facades;

use Config\Services;
use DateTimeInterface;
use Jengo\Auth\Authentication\Contracts\GuardInterface;
use Jengo\Auth\Authentication\DTOs\AuthResult;
use Jengo\Auth\Authentication\DTOs\TokenResult;
use Jengo\Auth\Entities\User;

class Auth
{
    public static function check(): bool
    {
        return Services::auth()->check();
    }

    public static function guest(): bool
    {
        return Services::auth()->guest();
    }

    public static function user(User|int|string|null $user = null): mixed
    {
        return Services::auth()->user($user);
    }

    public static function id(): ?int
    {
        return Services::auth()->id();
    }

    public static function attempt(array $credentials = [], bool $remember = false): AuthResult
    {
        return Services::auth()->attempt($credentials, $remember);
    }

    public static function login(User $user, bool $remember = false): void
    {
        Services::auth()->login($user, $remember);
    }

    public static function logout(): void
    {
        Services::auth()->logout();
    }

    public static function guard(?string $name = null): GuardInterface
    {
        return Services::auth()->guard($name);
    }

    public static function can(string $permission, mixed $resource = null, ?User $user = null): bool
    {
        return Services::auth()->can($permission, $resource, $user);
    }

    public static function cannot(string $permission, mixed $resource = null, ?User $user = null): bool
    {
        return Services::auth()->cannot($permission, $resource, $user);
    }

    public static function authorize(string $permission, mixed $resource = null, ?User $user = null): void
    {
        Services::auth()->authorize($permission, $resource, $user);
    }

    public static function hasRole(User|int $user, string $role): bool
    {
        return Services::auth()->hasRole($user, $role);
    }

    public static function isSuperAdmin(User|int $user): bool
    {
        return Services::auth()->isSuperAdmin($user);
    }

    public static function createTokenFor(
        User $user,
        string $name,
        array $abilities = ['*'],
        ?DateTimeInterface $expiresAt = null
    ): TokenResult {
        return Services::auth()->createTokenFor($user, $name, $abilities, $expiresAt);
    }
}
