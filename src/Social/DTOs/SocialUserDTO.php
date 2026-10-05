<?php

declare(strict_types=1);

namespace Jengo\Auth\Social\DTOs;

class SocialUserDTO
{
    public function __construct(
        public string $id,
        public ?string $email = null,
        public ?string $name = null,
        public ?string $avatar = null,
        public ?string $username = null,
        public ?string $accessToken = null,
        public ?string $refreshToken = null,
        public ?int $expiresIn = null,
        public array $raw = []
    ) {}

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'email'         => $this->email,
            'name'          => $this->name,
            'avatar'        => $this->avatar,
            'username'      => $this->username,
            'access_token'  => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_in'    => $this->expiresIn,
            'raw'           => $this->raw,
        ];
    }
}
