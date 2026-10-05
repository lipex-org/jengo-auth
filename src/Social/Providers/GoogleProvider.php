<?php

declare(strict_types=1);

namespace Jengo\Auth\Social\Providers;

use Jengo\Auth\Social\DTOs\SocialUserDTO;
use RuntimeException;

class GoogleProvider extends AbstractOAuthProvider
{
    public function getIdentifier(): string
    {
        return 'google';
    }

    protected function defaultConfig(): array
    {
        return [
            'scopes'      => ['openid', 'profile', 'email'],
            'auth_params' => [
                'access_type' => 'offline',
                'prompt'      => 'select_account',
            ],
        ];
    }

    protected function getAuthEndpoint(): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth';
    }

    protected function getTokenEndpoint(): string
    {
        return 'https://oauth2.googleapis.com/token';
    }

    protected function getUserInfoEndpoint(): string
    {
        return 'https://openidconnect.googleapis.com/v1/userinfo';
    }

    public function handleCallback(array $queryParams): SocialUserDTO
    {
        if (!empty($queryParams['error'])) {
            $err = (string) $queryParams['error'];
            $desc = (string) ($queryParams['error_description'] ?? '');
            throw new RuntimeException("Google authentication error: {$err} {$desc}");
        }

        $this->verifyState($queryParams);

        $code = (string) ($queryParams['code'] ?? '');
        if ($code === '') {
            throw new RuntimeException('Missing authorization code in Google callback.');
        }

        $tokenData = $this->requestAccessToken($code);
        $accessToken = (string) ($tokenData['access_token'] ?? '');
        $refreshToken = isset($tokenData['refresh_token']) ? (string) $tokenData['refresh_token'] : null;
        $expiresIn = isset($tokenData['expires_in']) ? (int) $tokenData['expires_in'] : null;

        $rawUser = $this->requestUserInfo($accessToken);

        $id = (string) ($rawUser['sub'] ?? '');
        if ($id === '') {
            throw new RuntimeException('Invalid user profile returned by Google: missing sub identifier.');
        }

        $email = !empty($rawUser['email']) ? (string) $rawUser['email'] : null;
        $name = !empty($rawUser['name']) ? (string) $rawUser['name'] : null;
        $avatar = !empty($rawUser['picture']) ? (string) $rawUser['picture'] : null;

        // Suggest a username if available (e.g. from email or name)
        $username = null;
        if ($email !== null) {
            $username = strtolower(explode('@', $email)[0]);
        }

        return new SocialUserDTO(
            id: $id,
            email: $email,
            name: $name,
            avatar: $avatar,
            username: $username,
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            expiresIn: $expiresIn,
            raw: $rawUser
        );
    }
}
