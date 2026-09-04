<?php

declare(strict_types=1);

namespace Jengo\Auth\Authentication\Authenticators;

use Config\Services;
use Jengo\Auth\Authentication\Contracts\GuardInterface;
use Jengo\Auth\Authentication\DTOs\AuthResult;
use Jengo\Auth\Entities\User;

class UniversalGuard implements GuardInterface
{
    protected TokenGuard $tokenGuard;
    protected SessionGuard $sessionGuard;
    protected ?GuardInterface $activeGuard = null;

    public function __construct(?TokenGuard $tokenGuard = null, ?SessionGuard $sessionGuard = null)
    {
        $this->tokenGuard   = $tokenGuard ?? new TokenGuard();
        $this->sessionGuard = $sessionGuard ?? new SessionGuard();
    }

    public function getTokenGuard(): TokenGuard
    {
        return $this->tokenGuard;
    }

    public function getSessionGuard(): SessionGuard
    {
        return $this->sessionGuard;
    }

    public function guard(string $name): GuardInterface
    {
        return auth()->guard($name);
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function user(): ?User
    {
        // 1. Check if Bearer token header or query parameter is provided
        $request = Services::request();
        $authHeader = $request->getHeaderLine('Authorization');
        $hasToken = ($authHeader && str_starts_with(strtolower($authHeader), 'bearer'))
            || $request->getGet('api_token') !== null;

        if ($hasToken) {
            $user = $this->tokenGuard->user();
            if ($user !== null) {
                $this->activeGuard = $this->tokenGuard;
                return $user;
            }
        }

        // 2. Fall back to Session guard
        $user = $this->sessionGuard->user();
        if ($user !== null) {
            $this->activeGuard = $this->sessionGuard;
            return $user;
        }

        return null;
    }

    public function id(): ?int
    {
        return $this->user()?->id;
    }

    public function attempt(array $credentials = [], bool $remember = false): AuthResult
    {
        return $this->sessionGuard->attempt($credentials, $remember);
    }

    public function login(User $user, bool $remember = false): void
    {
        $this->sessionGuard->login($user, $remember);
        $this->tokenGuard->setUser($user);
    }

    public function logout(): void
    {
        $this->tokenGuard->logout();
        $this->sessionGuard->logout();
        $this->activeGuard = null;
    }

    public function setUser(User $user): self
    {
        $this->sessionGuard->setUser($user);
        $this->tokenGuard->setUser($user);
        return $this;
    }
}
