<?php

declare(strict_types=1);

namespace Jengo\Auth\Entities;

use DateTimeInterface;
use Jengo\Auth\Authentication\DTOs\TokenResult;
use Jengo\Base\Entities\BaseEntity;
use Vima\Core\User\Fluent\UserDeny;
use Vima\Core\User\Fluent\UserGet;
use Vima\Core\User\Fluent\UserGrant;
use Vima\Core\User\Fluent\UserHas;
use Vima\Core\User\Fluent\UserIs;
use Vima\Core\User\Fluent\UserResource;
use Vima\Core\User\Fluent\UserRevoke;
use Vima\Core\User\Fluent\UserUndeny;

class User extends BaseEntity
{
    protected array $obfuscatedFields = ['id'];

    protected $casts = [
        'id'          => 'integer',
        'active'      => 'boolean',
        'last_active' => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
        'deleted_at'  => 'datetime',
    ];

    /**
     * Vima ID resolver hook.
     */
    public function vimaGetId(): int|string
    {
        return $this->id ?? $this->attributes['id'] ?? 0;
    }

    public function getId(): int|string
    {
        return $this->id ?? $this->attributes['id'] ?? 0;
    }

    /**
     * Create a new personal access token for the user.
     */
    public function createToken(string $name, array $abilities = ['*'], ?DateTimeInterface $expiresAt = null): TokenResult
    {
        return auth()->createTokenFor($this, $name, $abilities, $expiresAt);
    }

    /**
     * Get the primary email from identities.
     */
    public function getEmail(): ?string
    {
        if (isset($this->attributes['email'])) {
            return (string) $this->attributes['email'];
        }

        $identity = auth()->getUserIdentityModel()->where('user_id', $this->id)->where('type', 'email_password')->first();
        return $identity ? $identity->name : null;
    }

    /**
     * Contextual fluent Vima Resource for this user.
     */
    public function vima(): UserResource
    {
        return auth()->vima()->user($this);
    }

    /**
     * Check if user can perform permission or pass policy check.
     */
    public function can(string $permission, mixed ...$arguments): bool
    {
        return auth()->vima()->can($this, $permission, ...$arguments);
    }

    /**
     * Check if user cannot perform permission or pass policy check.
     */
    public function cannot(string $permission, mixed ...$arguments): bool
    {
        return ! $this->can($permission, ...$arguments);
    }

    /**
     * Fluent grant builder.
     */
    public function grant(): UserGrant
    {
        return $this->vima()->grant();
    }

    /**
     * Fluent deny builder (explicit deny).
     */
    public function deny(): UserDeny
    {
        return $this->vima()->deny();
    }

    /**
     * Fluent undeny builder.
     */
    public function undeny(): UserUndeny
    {
        return $this->vima()->undeny();
    }

    /**
     * Fluent revoke builder.
     */
    public function revoke(): UserRevoke
    {
        return $this->vima()->revoke();
    }

    /**
     * Fluent has inspector.
     */
    public function has(): UserHas
    {
        return $this->vima()->has();
    }

    /**
     * Fluent is inspector.
     */
    public function is(): UserIs
    {
        return $this->vima()->is();
    }

    /**
     * Fluent get inspector.
     */
    public function get(): UserGet
    {
        return $this->vima()->get();
    }

    /**
     * Check if user has a given role.
     */
    public function hasRole(string $role): bool
    {
        return $this->has()->role($role);
    }

    /**
     * Check if user is a Super Admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->is()->superAdmin();
    }
}
