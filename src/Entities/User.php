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
    public function createToken(
        string $name,
        array $abilities = ['*'],
        ?DateTimeInterface $expiresAt = null,
        ?string $prefix = null
    ): TokenResult {
        return auth()->createTokenFor($this, $name, $abilities, $expiresAt, $prefix);
    }

    /**
     * Get the primary email from identities.
     */
    public function getEmail(): ?string
    {
        if (isset($this->attributes['email']) && !empty($this->attributes['email'])) {
            return (string) $this->attributes['email'];
        }
        if (isset($this->email) && !empty($this->email)) {
            return (string) $this->email;
        }

        $userId = $this->getId();
        if (empty($userId)) {
            return null;
        }

        $identity = auth()->getUserIdentityModel()->where('user_id', $userId)->where('type', 'email_password')->first();
        if ($identity) {
            return $identity->name;
        }

        // Check if there is a social identity with extra.email
        $socialIdentity = auth()->getUserIdentityModel()->where('user_id', $userId)->like('type', 'oauth_', 'after')->first();
        if ($socialIdentity && !empty($socialIdentity->extra)) {
            $extra = is_string($socialIdentity->extra) ? json_decode($socialIdentity->extra, true) : (array) $socialIdentity->extra;
            if (!empty($extra['email'])) {
                return (string) $extra['email'];
            }
        }

        return null;
    }

    /**
     * Get the preferred username or identity name.
     */
    public function getUsername(): ?string
    {
        if (!empty($this->attributes['username'])) {
            return (string) $this->attributes['username'];
        }

        return $this->getEmail();
    }

    /**
     * Get user identifier with username preferred over email.
     */
    public function getUserIdentifier(): string
    {
        return $this->getUsername() ?? (string) $this->getId();
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

    /**
     * Check if user is banned.
     */
    public function isBanned(): bool
    {
        return ($this->status ?? 'active') === 'banned' || (! (bool) $this->active && ! empty($this->status_message));
    }

    /**
     * Check if user account is active and not banned.
     */
    public function isActive(): bool
    {
        return (bool) $this->active && ($this->status ?? 'active') !== 'banned';
    }

    /**
     * Ban the user.
     */
    public function ban(?string $message = null): self
    {
        $this->status = 'banned';
        $this->active = false;
        $this->status_message = $message;

        return $this;
    }

    /**
     * Unban the user.
     */
    public function unban(): self
    {
        $this->status = 'active';
        $this->active = true;
        $this->status_message = null;

        return $this;
    }

    /**
     * Check if user has any active two-factor factors enrolled.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return count(\Config\Services::twoFactor()->enrolledDriversFor($this)) > 0;
    }

    /**
     * Get all enrolled two-factor drivers for this user.
     */
    public function enrolledFactors(): array
    {
        return \Config\Services::twoFactor()->enrolledDriversFor($this);
    }

    /**
     * Get user's registered passkeys list.
     */
    public function passkeys(): array
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('auth_user_passkeys')) {
            return [];
        }

        return $db->table('auth_user_passkeys')
            ->where('user_id', $this->getId())
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Check if user has a password-based identity configured.
     */
    public function hasPassword(): bool
    {
        $identity = auth()->getUserIdentityModel()
            ->where('user_id', $this->getId())
            ->where('type', 'email_password')
            ->first();

        return $identity !== null && !empty($identity->secret);
    }

    /**
     * Set or provision a password for this user (creating or updating the email_password identity).
     */
    public function setPassword(string $password, ?string $email = null): self
    {
        $email = $email ?? $this->getEmail() ?? $this->attributes['email'] ?? null;
        if (empty($email)) {
            throw new \RuntimeException('Cannot set password: user has no valid email address.');
        }

        $identityModel = auth()->getUserIdentityModel();
        $identity = $identityModel
            ->where('user_id', $this->getId())
            ->where('type', 'email_password')
            ->first();

        $hashed = auth()->getHasher()->hash($password);

        if ($identity !== null) {
            $identityModel->update($identity->id, [
                'name'         => $email,
                'secret'       => $hashed,
                'force_reset'  => 0,
                'last_used_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $newIdentity = new UserIdentity([
                'user_id'      => $this->getId(),
                'type'         => 'email_password',
                'name'         => $email,
                'secret'       => $hashed,
                'force_reset'  => 0,
                'last_used_at' => date('Y-m-d H:i:s'),
            ]);
            $identityModel->insert($newIdentity);
        }

        return $this;
    }

    /**
     * Check if user has linked a specific social provider.
     */
    public function hasSocialIdentity(string $provider): bool
    {
        return auth()->getUserIdentityModel()
            ->where('user_id', $this->getId())
            ->where('type', 'oauth_' . $provider)
            ->first() !== null;
    }

    /**
     * Get all linked social identities for this user.
     */
    public function getSocialIdentities(): array
    {
        return auth()->getUserIdentityModel()
            ->where('user_id', $this->getId())
            ->like('type', 'oauth_', 'after')
            ->findAll();
    }

    /**
     * Get a formatted summary of all linked third-party / OAuth identities.
     *
     * @return array<array{id: int|string, provider: string, provider_user_id: string, email: ?string, name: ?string, avatar: ?string, created_at: ?string, last_used_at: ?string}>
     */
    public function getIdentitiesSummary(): array
    {
        $identities = $this->getSocialIdentities();
        $summary = [];

        foreach ($identities as $identity) {
            $provider = str_starts_with($identity->type, 'oauth_') ? substr($identity->type, 6) : $identity->type;
            $extra = is_string($identity->extra) ? json_decode($identity->extra, true) : (array) ($identity->extra ?? []);

            $summary[] = [
                'id'               => $identity->id,
                'provider'         => $provider,
                'provider_name'    => ucfirst($provider),
                'provider_user_id' => $identity->name,
                'email'            => $extra['email'] ?? null,
                'name'             => $extra['name'] ?? null,
                'avatar'           => $identity->secret2 ?? ($extra['picture'] ?? ($extra['avatar_url'] ?? null)),
                'created_at'       => $identity->created_at ? (string) $identity->created_at : null,
                'last_used_at'     => $identity->last_used_at ? (string) $identity->last_used_at : null,
            ];
        }

        return $summary;
    }
}

