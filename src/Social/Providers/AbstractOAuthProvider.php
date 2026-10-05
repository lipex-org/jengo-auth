<?php

declare(strict_types=1);

namespace Jengo\Auth\Social\Providers;

use CodeIgniter\HTTP\CURLRequest;
use Config\Services;
use Jengo\Auth\Social\Contracts\SocialProviderInterface;
use RuntimeException;

abstract class AbstractOAuthProvider implements SocialProviderInterface
{
    protected array $config;
    protected ?CURLRequest $httpClient = null;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->defaultConfig(), $config);
    }

    abstract protected function defaultConfig(): array;
    abstract protected function getAuthEndpoint(): string;
    abstract protected function getTokenEndpoint(): string;
    abstract protected function getUserInfoEndpoint(): string;

    public function setHttpClient(CURLRequest $client): self
    {
        $this->httpClient = $client;
        return $this;
    }

    protected function getHttpClient(): CURLRequest
    {
        if ($this->httpClient === null) {
            $this->httpClient = Services::curlrequest([
                'timeout'     => 10,
                'http_errors' => false,
            ]);
        }

        return $this->httpClient;
    }

    public function getClientId(): string
    {
        return (string) ($this->config['client_id'] ?? '');
    }

    public function getClientSecret(): string
    {
        return (string) ($this->config['client_secret'] ?? '');
    }

    public function getRedirectUri(): string
    {
        $uri = (string) ($this->config['redirect_uri'] ?? '');
        if ($uri !== '') {
            if (!str_starts_with($uri, 'http://') && !str_starts_with($uri, 'https://')) {
                return base_url($uri);
            }
            return $uri;
        }

        // Dynamically compute absolute redirect URI based on canonical route name 'auth.oauth.callback'
        return auth_url('auth.oauth.callback', $this->getIdentifier());
    }

    public function getScopes(): array
    {
        return (array) ($this->config['scopes'] ?? []);
    }

    /**
     * Generate standard OAuth2 authorization redirect URL with CSRF state protection.
     */
    public function getAuthUrl(array $options = []): string
    {
        $session = Services::session();
        $state = bin2hex(random_bytes(16));
        $session->set('oauth_state_' . $this->getIdentifier(), $state);

        $params = array_merge([
            'client_id'     => $this->getClientId(),
            'redirect_uri'  => $this->getRedirectUri(),
            'response_type' => 'code',
            'scope'         => implode(' ', $this->getScopes()),
            'state'         => $state,
        ], $this->config['auth_params'] ?? [], $options);

        return $this->getAuthEndpoint() . '?' . http_build_query($params);
    }

    /**
     * Verify CSRF state returned in OAuth callback.
     */
    protected function verifyState(array $queryParams): void
    {
        $session = Services::session();
        $savedState = $session->get('oauth_state_' . $this->getIdentifier());
        $returnedState = $queryParams['state'] ?? null;

        $session->remove('oauth_state_' . $this->getIdentifier());

        if (empty($savedState) || empty($returnedState) || !hash_equals((string) $savedState, (string) $returnedState)) {
            throw new RuntimeException('Invalid OAuth state parameter. Potential CSRF detected.');
        }
    }

    /**
     * Exchange authorization code for token response.
     */
    protected function requestAccessToken(string $code): array
    {
        $response = $this->getHttpClient()->post($this->getTokenEndpoint(), [
            'headers' => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => [
                'client_id'     => $this->getClientId(),
                'client_secret' => $this->getClientSecret(),
                'code'          => $code,
                'redirect_uri'  => $this->getRedirectUri(),
                'grant_type'    => 'authorization_code',
            ],
        ]);

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        if ($response->getStatusCode() >= 400 || !is_array($data) || empty($data['access_token'])) {
            $error = is_array($data) ? ($data['error_description'] ?? $data['error'] ?? $body) : $body;
            throw new RuntimeException("OAuth token exchange failed: {$error}");
        }

        return $data;
    }

    /**
     * Fetch user profile using access token.
     */
    protected function requestUserInfo(string $accessToken): array
    {
        $response = $this->getHttpClient()->get($this->getUserInfoEndpoint(), [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Accept'        => 'application/json',
                'User-Agent'    => 'Jengo-Auth-Social/1.0',
            ],
        ]);

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        if ($response->getStatusCode() >= 400 || !is_array($data)) {
            throw new RuntimeException("Failed to fetch user info from provider: {$body}");
        }

        return $data;
    }
}
