<?php

declare(strict_types=1);

namespace Jengo\Auth\Social\Providers;

use Jengo\Auth\Social\DTOs\SocialUserDTO;
use RuntimeException;

class GitHubProvider extends AbstractOAuthProvider
{
    public function getIdentifier(): string
    {
        return 'github';
    }

    protected function defaultConfig(): array
    {
        return [
            'scopes' => ['user:email', 'read:user'],
        ];
    }

    protected function getAuthEndpoint(): string
    {
        return 'https://github.com/login/oauth/authorize';
    }

    protected function getTokenEndpoint(): string
    {
        return 'https://github.com/login/oauth/access_token';
    }

    protected function getUserInfoEndpoint(): string
    {
        return 'https://api.github.com/user';
    }

    public function handleCallback(array $queryParams): SocialUserDTO
    {
        if (!empty($queryParams['error'])) {
            $err = (string) $queryParams['error'];
            $desc = (string) ($queryParams['error_description'] ?? '');
            throw new RuntimeException("GitHub authentication error: {$err} {$desc}");
        }

        $this->verifyState($queryParams);

        $code = (string) ($queryParams['code'] ?? '');
        if ($code === '') {
            throw new RuntimeException('Missing authorization code in GitHub callback.');
        }

        $tokenData = $this->requestAccessToken($code);
        $accessToken = (string) ($tokenData['access_token'] ?? '');
        $refreshToken = isset($tokenData['refresh_token']) ? (string) $tokenData['refresh_token'] : null;
        $expiresIn = isset($tokenData['expires_in']) ? (int) $tokenData['expires_in'] : null;

        $rawUser = $this->requestUserInfo($accessToken);

        $id = isset($rawUser['id']) ? (string) $rawUser['id'] : '';
        if ($id === '') {
            throw new RuntimeException('Invalid user profile returned by GitHub: missing id.');
        }

        $email = !empty($rawUser['email']) ? (string) $rawUser['email'] : null;

        // If email is private on GitHub profile, fetch from emails endpoint
        if ($email === null && $accessToken !== '') {
            $email = $this->fetchPrimaryEmail($accessToken);
        }

        $name = !empty($rawUser['name']) ? (string) $rawUser['name'] : (!empty($rawUser['login']) ? (string) $rawUser['login'] : null);
        $avatar = !empty($rawUser['avatar_url']) ? (string) $rawUser['avatar_url'] : null;
        $username = !empty($rawUser['login']) ? (string) $rawUser['login'] : null;

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

    protected function fetchPrimaryEmail(string $accessToken): ?string
    {
        try {
            $response = $this->getHttpClient()->get('https://api.github.com/user/emails', [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Accept'        => 'application/json',
                    'User-Agent'    => 'Jengo-Auth-Social/1.0',
                ],
            ]);

            $emails = json_decode((string) $response->getBody(), true);
            if (is_array($emails)) {
                foreach ($emails as $item) {
                    if (!empty($item['primary']) && !empty($item['verified'])) {
                        return (string) $item['email'];
                    }
                }
                if (isset($emails[0]['email'])) {
                    return (string) $emails[0]['email'];
                }
            }
        } catch (\Throwable) {
            // Ignore email lookup fallback error
        }

        return null;
    }
}
