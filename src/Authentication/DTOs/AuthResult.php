<?php

declare(strict_types=1);

namespace Jengo\Auth\Authentication\DTOs;

use Jengo\Auth\Entities\User;

class AuthResult
{
    public function __construct(
        public bool $success,
        public ?User $user = null,
        public ?string $error = null,
        public array $extra = []
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getMessage(): ?string
    {
        return $this->error;
    }

    public static function success(?User $user = null, array $extra = []): self
    {
        return new self(success: true, user: $user, extra: $extra);
    }

    public static function failure(?string $error = null, array $extra = []): self
    {
        return new self(success: false, error: $error, extra: $extra);
    }
}
