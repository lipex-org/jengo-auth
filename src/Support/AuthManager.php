<?php

declare(strict_types=1);

namespace Jengo\Auth\Support;

use CodeIgniter\Router\RouteCollection;
use Config\Services;
use DateTimeInterface;
use InvalidArgumentException;
use Jengo\Auth\Authentication\Authenticators\SessionGuard;
use Jengo\Auth\Authentication\Authenticators\TokenGuard;
use Jengo\Auth\Authentication\Authenticators\UniversalGuard;
use Jengo\Auth\Authentication\Contracts\GuardInterface;
use Jengo\Auth\Authentication\DTOs\AuthResult;
use Jengo\Auth\Authentication\DTOs\TokenResult;
use Jengo\Auth\Authentication\Password\PasswordHasher;
use Jengo\Auth\Authentication\Throttling\RateLimiter;
use Jengo\Auth\DTOs\AuthResponseData;
use Jengo\Auth\Entities\User;
use Jengo\Auth\Models\UserIdentityModel;
use Jengo\Auth\Models\UserModel;
use Jengo\Auth\Models\UserTokenModel;
use Jengo\Auth\Modifiers\StandardViewModifier;
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
    protected ?string $defaultGuard = null;
    protected array $guards = [];
    protected array $customCreators = [];
    protected PasswordHasher $hasher;
    protected RateLimiter $rateLimiter;
    protected UserModel $userModel;
    protected UserIdentityModel $identityModel;
    protected UserTokenModel $tokenModel;

    public function __construct(
        ?GuardInterface $defaultGuardInstance = null,
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

        if ($defaultGuardInstance !== null) {
            $this->guards['universal'] = $defaultGuardInstance;
        }
    }

    /**
     * Register a custom guard creator closure.
     */
    public function extend(string $name, callable $callback): self
    {
        $this->customCreators[$name] = $callback;
        unset($this->guards[$name]);

        return $this;
    }

    /**
     * Explicitly set a guard instance for a given name.
     */
    public function setGuard(string $name, GuardInterface $guard): self
    {
        $this->guards[$name] = $guard;

        return $this;
    }

    /**
     * Get the default guard name.
     */
    public function getDefaultDriver(): string
    {
        return $this->defaultGuard ?? config('Auth')->defaultGuard ?? 'universal';
    }

    /**
     * Set the default guard name.
     */
    public function setDefaultDriver(string $name): self
    {
        $this->defaultGuard = $name;

        return $this;
    }

    /**
     * Switch or resolve a specific guard driver.
     */
    public function guard(?string $name = null): GuardInterface
    {
        $name = $name ?? $this->getDefaultDriver();

        // Standardize common aliases
        $normalizedName = match (strtolower($name)) {
            'web'            => 'session',
            'api', 'bearer'  => 'token',
            default          => $name,
        };

        if (isset($this->guards[$normalizedName])) {
            return $this->guards[$normalizedName];
        }

        // 1. Check custom creators (auth()->extend('jwt', fn() => ...))
        if (isset($this->customCreators[$normalizedName])) {
            return $this->guards[$normalizedName] = ($this->customCreators[$normalizedName])($this);
        }

        // 2. Built-in Session Guard
        if ($normalizedName === 'session') {
            return $this->guards['session'] = new SessionGuard($this->userModel, $this->identityModel, $this->hasher);
        }

        // 3. Built-in Token Guard
        if ($normalizedName === 'token') {
            return $this->guards['token'] = new TokenGuard($this->userModel);
        }

        // 4. Built-in Universal Guard
        if ($normalizedName === 'universal') {
            $tokenGuard = $this->guard('token');
            $sessionGuard = $this->guard('session');

            return $this->guards['universal'] = new UniversalGuard(
                $tokenGuard instanceof TokenGuard ? $tokenGuard : null,
                $sessionGuard instanceof SessionGuard ? $sessionGuard : null
            );
        }

        // 5. Config-registered custom guard class mapping
        $configGuards = (array) (config('Auth')->guards ?? []);
        if (isset($configGuards[$normalizedName]) && class_exists($configGuards[$normalizedName])) {
            $class = $configGuards[$normalizedName];
            return $this->guards[$normalizedName] = new $class();
        }

        throw new InvalidArgumentException("Authentication guard [{$name}] is not defined.");
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
        return $this->guard()->check();
    }

    /**
     * Determine if current request user is guest.
     */
    public function guest(): bool
    {
        return $this->guard()->guest();
    }

    /**
     * Get the authenticated user entity.
     */
    public function currentUser(): ?User
    {
        return $this->guard()->user();
    }

    /**
     * Get the authenticated user ID.
     */
    public function id(): ?int
    {
        return $this->guard()->id();
    }

    /**
     * Attempt login with credentials.
     */
    public function attempt(array $credentials = [], bool $remember = false): AuthResult
    {
        return $this->guard()->attempt($credentials, $remember);
    }

    /**
     * Manually log in a user.
     */
    public function login(User $user, bool $remember = false): void
    {
        $this->guard()->login($user, $remember);
    }

    /**
     * Log out current user.
     */
    public function logout(): void
    {
        $this->guard()->logout();
    }

    /**
     * Set the active user on the current guard.
     */
    public function setUser(User $user): self
    {
        $guard = $this->guard();
        if (method_exists($guard, 'setUser')) {
            $guard->setUser($user);
        }

        return $this;
    }

    /**
     * Check permission or policy on resource.
     */
    public function can(string $permission, mixed ...$arguments): bool
    {
        $user = null;

        if (! empty($arguments) && $arguments[count($arguments) - 1] instanceof User) {
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
        if (! empty($arguments) && $arguments[count($arguments) - 1] instanceof User) {
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
     * Create personal access token for user.
     */
    public function createTokenFor(
        User $user,
        string $name,
        array $abilities = ['*'],
        ?DateTimeInterface $expiresAt = null
    ): TokenResult {
        $tokenGuard = $this->guard('token');
        if ($tokenGuard instanceof TokenGuard) {
            return $tokenGuard->createToken($user, $name, $abilities, $expiresAt);
        }

        throw new \RuntimeException("Current token guard does not support personal access token generation.");
    }

    /**
     * Publish standard auth routes to a CodeIgniter route collection.
     */
    public function routes(RouteCollection $routes, array $options = []): void
    {
        RouteRegistrar::routes($routes, $options);
    }

    protected ?ResponseHandler $responseHandler = null;

    /**
     * Get the ResponseHandler instance.
     */
    public function getResponseHandler(): ResponseHandler
    {
        return $this->responseHandler ??= new ResponseHandler();
    }

    /**
     * Set a custom ResponseHandler instance.
     */
    public function setResponseHandler(ResponseHandler $responseHandler): self
    {
        $this->responseHandler = $responseHandler;

        return $this;
    }

    /**
     * Render an auth action response through the configured ResponseHandler.
     */
    public function renderResponse(string $action, AuthResponseData $data, ?\CodeIgniter\HTTP\RequestInterface $request = null): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->getResponseHandler()->render($action, $data, $request);
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
