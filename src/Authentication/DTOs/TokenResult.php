<?php

declare(strict_types=1);

namespace Jengo\Auth\Authentication\DTOs;

use Jengo\Auth\Entities\UserToken;

class TokenResult
{
    public function __construct(
        public UserToken $accessToken,
        public string $plainTextToken
    ) {}

    public function toArray(): array
    {
        return [
            'token_type'   => 'Bearer',
            'access_token' => $this->plainTextToken,
            'name'         => $this->accessToken->name,
            'abilities'    => $this->accessToken->abilities,
            'expires_at'   => $this->accessToken->expires_at?->format('c'),
        ];
    }
}
