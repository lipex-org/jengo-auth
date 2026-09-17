<?php

declare(strict_types=1);

namespace Jengo\Auth\Authentication\Authenticators;

use Config\Services;
use DateTimeInterface;
use Jengo\Auth\Authentication\Contracts\GuardInterface;
use Jengo\Auth\Authentication\DTOs\AuthResult;
use Jengo\Auth\Authentication\DTOs\TokenResult;
use Jengo\Auth\Entities\User;
use Jengo\Auth\Entities\UserToken;
use Jengo\Auth\Models\UserModel;
use Jengo\Auth\Models\UserTokenModel;

class TokenGuard implements GuardInterface
{
    protected ?User $user = null;
    protected ?UserToken $currentToken = null;
    protected UserModel $userModel;
    protected UserTokenModel $tokenModel;

    public function __construct(?UserModel $userModel = null, ?UserTokenModel $tokenModel = null)
    {
        $this->userModel  = $userModel ?? new UserModel();
        $this->tokenModel = $tokenModel ?? new UserTokenModel();
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

        $tokenString = $this->extractTokenFromRequest();
        if (! $tokenString) {
            return null;
        }

        $token = $this->tokenModel->findByPlainTextToken($tokenString);
        if (! $token || $token->isExpired()) {
            return null;
        }

        $user = $this->userModel->find($token->user_id);
        if (! $user || ! $user->active || $user->status === 'banned') {
            return null;
        }

        // Update last_used_at on token
        $token->last_used_at = date('Y-m-d H:i:s');
        if ($token->hasChanged()) {
            $this->tokenModel->save($token);
        }

        $this->currentToken = $token;
        $this->user         = $user;

        return $this->user;
    }

    public function id(): ?int
    {
        return $this->user()?->id;
    }

    public function currentToken(): ?UserToken
    {
        return $this->currentToken;
    }

    public function attempt(array $credentials = [], bool $remember = false): AuthResult
    {
        $sessionGuard = new SessionGuard($this->userModel);
        return $sessionGuard->attempt($credentials, $remember);
    }

    public function login(User $user, bool $remember = false): void
    {
        $this->setUser($user);
    }

    public function logout(): void
    {
        if ($this->currentToken !== null) {
            $this->tokenModel->delete($this->currentToken->id);
            $this->currentToken = null;
        }

        $this->user = null;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Issue a new personal access token for a user.
     */
    public function createToken(
        User $user,
        string $name,
        array $abilities = ['*'],
        ?DateTimeInterface $expiresAt = null
    ): TokenResult {
        $plainText = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainText);

        $token = new UserToken([
            'user_id'    => $user->id,
            'name'       => $name,
            'token_hash' => $tokenHash,
            'abilities'  => $abilities,
            'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
        ]);

        $tokenId = $this->tokenModel->insert($token);
        $token->id = (int) $tokenId;

        return new TokenResult($token, $plainText);
    }

    /**
     * Revoke all tokens for a user.
     */
    public function revokeAllTokens(User $user): bool
    {
        return (bool) $this->tokenModel->where('user_id', $user->id)->delete();
    }

    /**
     * Extract bearer token from Authorization header or query parameter.
     */
    protected function extractTokenFromRequest(): ?string
    {
        $request = Services::request();

        // 1. Authorization header
        $authHeader = $request->getHeaderLine('Authorization');
        if (! $authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = (string) $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (! $authHeader && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if ($authHeader && preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // 2. Query parameter fallback for development / webhooks
        $queryToken = $request->getGet('api_token') ?? $request->getGet('token') ?? $_GET['api_token'] ?? $_GET['token'] ?? null;
        if (is_string($queryToken) && $queryToken !== '') {
            return $queryToken;
        }

        return null;
    }
}
