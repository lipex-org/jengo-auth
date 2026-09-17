<?php

declare(strict_types=1);

namespace Jengo\Auth\Entities;

use DateTime;
use Jengo\Base\Entities\BaseEntity;

class UserToken extends BaseEntity
{
    protected array $obfuscatedFields = ['id', 'user_id'];

    protected array $hidden = ['token_hash'];

    protected $casts = [
        'id'           => 'integer',
        'user_id'      => 'integer',
        'abilities'    => 'json-array',
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    /**
     * Determine if the token has a specific ability.
     */
    public function can(string $ability): bool
    {
        $abilities = $this->abilities ?? [];
        if (in_array('*', $abilities, true)) {
            return true;
        }

        foreach ($abilities as $tokenAbility) {
            if ($tokenAbility === $ability) {
                return true;
            }
            if (str_ends_with($tokenAbility, '*') && str_starts_with($ability, substr($tokenAbility, 0, -1))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the token does not have a specific ability.
     */
    public function cannot(string $ability): bool
    {
        return ! $this->can($ability);
    }

    /**
     * Determine if the token is expired.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        $expiresAt = $this->expires_at instanceof DateTime ? $this->expires_at : new DateTime((string) $this->expires_at);
        return $expiresAt->getTimestamp() <= time();
    }
}
