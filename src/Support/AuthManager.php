<?php

declare(strict_types=1);

namespace Jengo\Auth\Support;

use Config\Services;
use DateTimeInterface;
use Jengo\Auth\Authentication\Authenticators\SessionGuard;
use Jengo\Auth\Authentication\Authenticators\TokenGuard;
use Jengo\Auth\Authentication\Authenticators\UniversalGuard;
use Jengo\Auth\Authentication\Contracts\GuardInterface;
use Jengo\Auth\Authentication\DTOs\AuthResult;
use Jengo\Auth\Authentication\DTOs\TokenResult;
use Jengo\Auth\Authentication\Password\PasswordHasher;
use Jengo\Auth\Authentication\Throttling\RateLimiter;
use Jengo\Auth\Entities\User;
use Jengo\Auth\Models\UserIdentityModel;
use Jengo\Auth\Models\UserModel;
use Jengo\Auth\Models\UserTokenModel;
use Vima\Core\Permission\Fluent\PermissionResource;
use Vima\Core\Permission\Services\PermissionService;
use Vima\Core\Policy\Services\PolicyRegistry;
use Vima\Core\Role\Fluent\RoleResource;
use Vima\Core\Role\Services\RoleService;
use Vima\Core\User\Fluent\UserResource;
use Vima\Core\Vima;
use Vima\Core\VimaManager;

class AuthManager
{
    protected UniversalGuard $universalGuard;
    protected PasswordHasher $hasher;
    protected RateLimiter $rateLimiter;
    protected UserModel $userModel;
    protected UserIdentityModel $identityModel;
    protected UserTokenModel $tokenModel;

    public function __construct(
        ?UniversalGuard $universalGuard = null,
        ?PasswordHasher $hasher = null,
        ?RateLimiter $rateLimiter = null,
        ?UserModel $userModel = null,
        ?UserIdentityModel $identityModel = null,
        ?UserTokenModel $tokenModel = null
    ) {
        $this->userModel      = $userModel ?? new UserModel();
        $this->identityModel  = $identityModel ?? new UserIdentityModel();
        $this->tokenModel     = $tokenModel ?? new UserTokenModel();
        $this->hasher         = $hasher ?? new PasswordHasher();
        $this->rateLimiter    = $rateLimiter ?? new RateLimiter();
        $this->universalGuard = $universalGuard ?? new UniversalGuard(
            new TokenGuard($this->userModel),
            new SessionGuard($this->userModel, $this->identityModel, $this->hasher)
        );
    }

    /**
     * Access the underlying Vima Access Manager.
     */
    public function vima(): VimaManager
    {
        return Services::vima();
    }

    /**
     * Determine if current request user is authenticated.
     */
    public function check(): bool
    {
        return $this->universalGuard->check();
    }

    /**
     * Determine if current request user is guest.
     */
    public function guest(): bool
    {
        return $this->universalGuard->guest();
    }

    /**
     * Get the authenticated user entity.
     */
    public function currentUser(): ?User
    {
        return $this->universalGuard->user();
    }

    /**
     * Get the authenticated user ID.
     */
    public function id(): ?int
    {
        return $this->universalGuard->id();
    }

    /**
     * Attempt login with credentials.
     */
    public function attempt(array $credentials = [], bool $remember = false): AuthResult
    {
        return $this->universalGuard->attempt($credentials, $remember);
    }

    /**
     * Manually log in a user.
     */
    public function login(User $user, bool $remember = false): void
    {
        $this->universalGuard->login($user, $remember);
    }

    /**
     * Log out current user.
     */
    public function logout(): void
    {
        $this->universalGuard->logout();
    }

    /**
     * Switch or get specific guard.
     */
    public function guard(?string $name = null): GuardInterface
    {
        if ($name === null) {
            return $this->universalGuard;
        }
        return $this->universalGuard->guard($name);
    }

    /**
     * Check permission or policy on resource.
     */
    public function can(string $permission, mixed ...$arguments): bool
    {
        $user = null;

        // If arguments has a User passed or we resolve current user
        if (!empty($arguments) && $arguments[count($arguments) - 1] instanceof User) {
            $user = array_pop($arguments);
        } else {
            $user = $this->currentUser();
        }

        if ($user === null) {
            return false;
        }

        return $this->vima()->can($user, $permission, ...$arguments);
    }

    /**
     * Check inverse of can().
     */
    public function cannot(string $permission, mixed ...$arguments): bool
    {
        return ! $this->can($permission, ...$arguments);
    }

    /**
     * Authorize an action or throw AccessDeniedException.
     */
    public function authorize(string $permission, mixed ...$arguments): void
    {
        $user = null;
        if (!empty($arguments) && $arguments[count($arguments) - 1] instanceof User) {
            $user = array_pop($arguments);
        } else {
            $user = $this->currentUser();
        }

        if ($user === null) {
            throw new \Jengo\Auth\Exceptions\AccessDeniedException("Unauthenticated user cannot perform [{$permission}].", 401, null, $permission);
        }

        $this->vima()->authorize($user, $permission, ...$arguments);
    }

    /**
     * User access / Fluent resource manager.
     * If called with no arguments, returns current authenticated User entity.
     * If called with a User, integer ID, or string ID, returns Vima UserResource.
     */
    public function user(mixed $user = null): mixed
    {
        if ($user === null) {
            return $this->currentUser();
        }

        if (is_int($user) || is_string($user)) {
            $userObj = is_numeric($user)
                ? $this->userModel->find((int) $user)
                : $this->userModel->where('username', $user)->first();

            if ($userObj) {
                return $this->vima()->user($userObj);
            }

            return $this->vima()->user(new User(['id' => $user]));
        }

        return $this->vima()->user($user);
    }

    /**
     * Contextual fluent Vima Role resource.
     */
    public function role(mixed $role): RoleResource
    {
        return $this->vima()->role($role);
    }

    /**
     * Contextual fluent Vima Permission resource.
     */
    public function permission(mixed $permission): PermissionResource
    {
        return $this->vima()->permission($permission);
    }

    /**
     * Global Vima Role Service.
     */
    public function roles(): RoleService
    {
        return Vima::roles();
    }

    /**
     * Global Vima Permission Service.
     */
    public function permissions(): PermissionService
    {
        return Vima::permissions();
    }

    /**
     * Global Vima Policy Registry.
     */
    public function policies(): PolicyRegistry
    {
        return Vima::policies();
    }

    /**
     * Check if user has role.
     */
    public function hasRole(mixed $user, string $role): bool
    {
        return $this->vima()->user($user)->has()->role($role);
    }

    /**
     * Check if user is superadmin.
     */
    public function isSuperAdmin(mixed $user): bool
    {
        return $this->vima()->user($user)->is()->superAdmin();
    }

    /**
     * Create token for user.
     */
    public function createTokenFor(
        User $user,
        string $name,
        array $abilities = ['*'],
        ?DateTimeInterface $expiresAt = null
    ): TokenResult {
        return $this->universalGuard->getTokenGuard()->createToken($user, $name, $abilities, $expiresAt);
    }

    /**
     * Publish standard auth routes to a CodeIgniter route collection.
     */
    public function routes(\CodeIgniter\Router\RouteCollection $routes, array $options = []): void
    {
        RouteRegistrar::routes($routes, $options);
    }

    /**
     * Render an auth action response through the configured ResponseModifier.
     */
    public function renderResponse(string $action, \Jengo\Auth\DTOs\AuthResponseData $data, ?\CodeIgniter\HTTP\RequestInterface $request = null): \CodeIgniter\HTTP\ResponseInterface
    {
        $modifierClass = config('Auth')->responseModifier ?? \Jengo\Auth\Modifiers\StandardViewModifier::class;
        $modifier = new $modifierClass();
        $req = $request ?? Services::request();
        return $modifier->modify($action, $data, $req);
    }

    protected ?\Jengo\Auth\Contracts\NotificationSenderInterface $notifier = null;

    /**
     * Get the configured notification sender instance.
     */
    public function getNotifier(): \Jengo\Auth\Contracts\NotificationSenderInterface
    {
        if ($this->notifier !== null) {
            return $this->notifier;
        }

        $notifierClass = config('Auth')->notifier ?? \Jengo\Auth\Notifications\DefaultEmailNotifier::class;
        return $this->notifier = new $notifierClass();
    }

    /**
     * Set a custom notification sender instance (useful for testing or dynamic swapping).
     */
    public function setNotifier(\Jengo\Auth\Contracts\NotificationSenderInterface $notifier): self
    {
        $this->notifier = $notifier;
        return $this;
    }

    // Models & Services getters
    public function getUserModel(): UserModel
    {
        return $this->userModel;
    }

    public function getUserIdentityModel(): UserIdentityModel
    {
        return $this->identityModel;
    }

    public function getUserTokenModel(): UserTokenModel
    {
        return $this->tokenModel;
    }

    public function getHasher(): PasswordHasher
    {
        return $this->hasher;
    }

    public function getRateLimiter(): RateLimiter
    {
        return $this->rateLimiter;
    }
}
