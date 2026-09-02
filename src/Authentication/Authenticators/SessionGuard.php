<?php

declare(strict_types=1);

namespace Jengo\Auth\Authentication\Authenticators;

use Config\Services;
use Jengo\Auth\Authentication\Contracts\GuardInterface;
use Jengo\Auth\Authentication\DTOs\AuthResult;
use Jengo\Auth\Authentication\Password\PasswordHasher;
use Jengo\Auth\Entities\User;
use Jengo\Auth\Models\UserIdentityModel;
use Jengo\Auth\Models\UserModel;

class SessionGuard implements GuardInterface
{
    protected ?User $user = null;
    protected UserModel $userModel;
    protected UserIdentityModel $identityModel;
    protected PasswordHasher $hasher;
    protected string $sessionKey = 'auth_user_id';

    public function __construct(
        ?UserModel $userModel = null,
        ?UserIdentityModel $identityModel = null,
        ?PasswordHasher $hasher = null
    ) {
        $this->userModel     = $userModel ?? new UserModel();
        $this->identityModel = $identityModel ?? new UserIdentityModel();
        $this->hasher        = $hasher ?? new PasswordHasher();
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
        if ($this->user !== null) {
            return $this->user;
        }

        $session = Services::session();
        $userId = $session->get($this->sessionKey);

        if ($userId) {
            $user = $this->userModel->find($userId);
            if ($user && $user->active) {
                $this->user = $user;
                return $this->user;
            }
        }

        // Try remember-me cookie
        $this->user = $this->recallUserFromCookie();

        return $this->user;
    }

    public function id(): ?int
    {
        return $this->user()?->id;
    }

    public function attempt(array $credentials = [], bool $remember = false): AuthResult
    {
        $identifier = $credentials['email'] ?? $credentials['username'] ?? null;
        $password   = $credentials['password'] ?? null;

        if (! $identifier || ! $password) {
            return new AuthResult(false, null, 'Email/Username and Password are required.');
        }

        // Find user by identifier
        $user = $this->userModel->findByIdentifier((string) $identifier);
        if (! $user) {
            return new AuthResult(false, null, 'Invalid credentials.');
        }

        if (! $user->active) {
            return new AuthResult(false, null, 'User account is inactive or banned.');
        }

        // Verify password against email_password identity
        $identity = $this->identityModel
            ->where('user_id', $user->id)
            ->where('type', 'email_password')
            ->first();

        if (! $identity || ! $this->hasher->verify((string) $password, (string) $identity->secret)) {
            return new AuthResult(false, null, 'Invalid credentials.');
        }

        // Check if password needs rehashing
        if ($this->hasher->needsRehash((string) $identity->secret)) {
            $identity->secret = $this->hasher->hash((string) $password);
            $this->identityModel->save($identity);
        }

        // Log in user
        $this->login($user, $remember);

        return new AuthResult(true, $user);
    }

    public function login(User $user, bool $remember = false): void
    {
        $this->setUser($user);

        $session = Services::session();
        if (session_status() === PHP_SESSION_ACTIVE) {
            $session->regenerate();
        }
        $session->set($this->sessionKey, $user->id);

        // Update last_active
        $this->userModel->update($user->id, ['last_active' => date('Y-m-d H:i:s')]);

        if ($remember) {
            $this->createRememberCookie($user);
        }
    }

    public function logout(): void
    {
        $this->user = null;

        $session = Services::session();
        $session->remove($this->sessionKey);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $session->regenerate();
        }

        // Clear remember cookie
        $this->clearRememberCookie();
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    protected function createRememberCookie(User $user): void
    {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + (30 * 86400));

        // Save remember token as an identity
        $identity = $this->identityModel
            ->where('user_id', $user->id)
            ->where('type', 'remember_token')
            ->first();

        if (! $identity) {
            $identity = new \Jengo\Auth\Entities\UserIdentity([
                'user_id' => $user->id,
                'type'    => 'remember_token',
            ]);
        }

        $identity->name    = $selector;
        $identity->secret  = hash('sha256', $validator);
        $identity->expires = $expires;
        $this->identityModel->save($identity);

        $response = Services::response();
        $response->setCookie('remember_token', "{$selector}:{$validator}", 30 * 86400, '', '', '', true, true);
    }

    protected function recallUserFromCookie(): ?User
    {
        $request = Services::request();
        $cookie = $request->getCookie('remember_token');

        if (! $cookie || ! str_contains($cookie, ':')) {
            return null;
        }

        [$selector, $validator] = explode(':', $cookie, 2);

        $identity = $this->identityModel
            ->where('type', 'remember_token')
            ->where('name', $selector)
            ->first();

        if (! $identity || ! hash_equals($identity->secret, hash('sha256', $validator))) {
            return null;
        }

        if ($identity->expires && strtotime((string) $identity->expires) < time()) {
            return null;
        }

        $user = $this->userModel->find($identity->user_id);
        if ($user && $user->active) {
            $session = Services::session();
            $session->set($this->sessionKey, $user->id);
            return $user;
        }

        return null;
    }

    protected function clearRememberCookie(): void
    {
        $response = Services::response();
        $response->deleteCookie('remember_token');
    }
}
