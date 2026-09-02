<?php

declare(strict_types=1);

namespace Jengo\Auth\Authentication\Contracts;

use Jengo\Auth\Authentication\DTOs\AuthResult;
use Jengo\Auth\Entities\User;

interface GuardInterface
{
    /**
     * Determine if the current request is from an authenticated user.
     */
    public function check(): bool;

    /**
     * Determine if the current request is from a guest (unauthenticated).
     */
    public function guest(): bool;

    /**
     * Get the currently authenticated user.
     */
    public function user(): ?User;

    /**
     * Get the ID of the currently authenticated user.
     */
    public function id(): ?int;

    /**
     * Attempt to authenticate a user using given credentials.
     */
    public function attempt(array $credentials = [], bool $remember = false): AuthResult;

    /**
     * Log a user into the application.
     */
    public function login(User $user, bool $remember = false): void;

    /**
     * Log the user out of the application.
     */
    public function logout(): void;

    /**
     * Set the current user.
     */
    public function setUser(User $user): self;
}
