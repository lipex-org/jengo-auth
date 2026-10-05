<?php

declare(strict_types=1);

namespace Jengo\Auth\Social;

use CodeIgniter\Events\Events;
use Config\Services;
use InvalidArgumentException;
use Jengo\Auth\Entities\User;
use Jengo\Auth\Entities\UserIdentity;
use Jengo\Auth\Social\Contracts\SocialProviderInterface;
use Jengo\Auth\Social\DTOs\SocialUserDTO;
use Jengo\Auth\Social\Providers\GitHubProvider;
use Jengo\Auth\Social\Providers\GoogleProvider;
use RuntimeException;

class SocialManager
{
    /**
     * Instantiated provider drivers cache.
     */
    protected array $drivers = [];

    /**
     * Custom extended driver factories.
     */
    protected array $customCreators = [];

    /**
     * Built-in driver map.
     */
    protected array $builtInDrivers = [
        'google' => GoogleProvider::class,
        'github' => GitHubProvider::class,
    ];

    /**
     * Social config cache.
     */
    protected array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (array) (config('Auth')->social ?? []);
    }

    /**
     * Check if social authentication feature is enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    /**
     * Get or instantiate a provider driver.
     */
    public function driver(string $provider): SocialProviderInterface
    {
        if (isset($this->drivers[$provider])) {
            return $this->drivers[$provider];
        }

        return $this->drivers[$provider] = $this->createDriver($provider);
    }

    /**
     * Register a custom provider driver factory.
     */
    public function extend(string $provider, callable $callback): self
    {
        $this->customCreators[$provider] = $callback;
        unset($this->drivers[$provider]);

        return $this;
    }

    /**
     * Create driver instance.
     */
    protected function createDriver(string $provider): SocialProviderInterface
    {
        if (isset($this->customCreators[$provider])) {
            $instance = call_user_func($this->customCreators[$provider], $this->getProviderConfig($provider));
            if (!$instance instanceof SocialProviderInterface) {
                throw new InvalidArgumentException("Custom social driver [{$provider}] must implement SocialProviderInterface.");
            }
            return $instance;
        }

        $providerConfig = $this->getProviderConfig($provider);
        $driverClass = $providerConfig['driver'] ?? ($this->builtInDrivers[$provider] ?? null);

        if ($driverClass === null || !class_exists($driverClass)) {
            throw new InvalidArgumentException("Unsupported social OAuth provider [{$provider}].");
        }

        return new $driverClass($providerConfig);
    }

    /**
     * Get provider configuration options.
     */
    public function getProviderConfig(string $provider): array
    {
        return (array) ($this->config['providers'][$provider] ?? []);
    }

    /**
     * Check if a specific provider is configured and enabled.
     */
    public function hasProvider(string $provider): bool
    {
        $cfg = $this->getProviderConfig($provider);
        return !empty($cfg['enabled']) || isset($this->customCreators[$provider]);
    }

    /**
     * Get list of all available and configured social authentication providers.
     * Returns an array of items with provider id, display name, and login URL.
     *
     * @return array<array{id: string, name: string, url: string}>
     */
    public function getAvailableProviders(): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $available = [];
        $providersConfig = (array) ($this->config['providers'] ?? []);

        // Include built-in configured providers
        foreach ($providersConfig as $providerId => $providerConfig) {
            $enabled = (bool) ($providerConfig['enabled'] ?? false);
            $hasClientId = ! empty($providerConfig['client_id']);

            if ($enabled && $hasClientId) {
                $displayName = ucfirst((string) $providerId);
                $available[] = [
                    'id'   => $providerId,
                    'name' => $displayName,
                    'url'  => function_exists('auth_url') ? auth_url('auth.oauth.redirect', $providerId) : "/oauth/{$providerId}",
                ];
            }
        }

        // Include custom extended providers
        foreach (array_keys($this->customCreators) as $customProviderId) {
            // Avoid duplicate if already added
            if (array_filter($available, fn($item) => $item['id'] === $customProviderId) === []) {
                $available[] = [
                    'id'   => $customProviderId,
                    'name' => ucfirst((string) $customProviderId),
                    'url'  => function_exists('auth_url') ? auth_url('auth.oauth.redirect', $customProviderId) : "/oauth/{$customProviderId}",
                ];
            }
        }

        return $available;
    }

    /**
     * Handle incoming OAuth callback, find or create the User, and return the User entity.
     *
     * @param string $provider The provider key (e.g. 'google', 'github')
     * @param array $queryParams Request query params (containing code, state, etc.)
     */
    public function handleCallback(string $provider, array $queryParams): User
    {
        $driver = $this->driver($provider);
        $socialUser = $driver->handleCallback($queryParams);

        return $this->findOrCreateUser($provider, $socialUser);
    }

    /**
     * Find existing user by social identity or create a new user and link the identity.
     */
    public function findOrCreateUser(string $provider, SocialUserDTO $socialUser): User
    {
        $auth = auth();
        $identityModel = $auth->getUserIdentityModel();
        $userModel = $auth->getUserModel();

        $identityType = 'oauth_' . $provider;

        // 1. Look up by exact social identity
        $identity = $identityModel->where('type', $identityType)
            ->where('name', $socialUser->id)
            ->first();

        if ($identity !== null) {
            $user = $userModel->find($identity->user_id);
            if ($user !== null) {
                // Update latest token and metadata
                $identityModel->update($identity->id, [
                    'secret'       => $socialUser->accessToken,
                    'secret2'      => $socialUser->avatar,
                    'extra'        => json_encode($socialUser->raw),
                    'last_used_at' => date('Y-m-d H:i:s'),
                ]);

                Events::trigger('socialLogin', $user, $provider, $socialUser);
                return $user;
            }
        }

        // 2. Check if an account already exists with the same verified email (if enabled)
        $autoLink = (bool) ($this->config['auto_link_verified_email'] ?? true);
        if ($autoLink && !empty($socialUser->email)) {
            $emailIdentity = $identityModel->where('type', 'email_password')
                ->where('name', $socialUser->email)
                ->first();
            if ($emailIdentity !== null) {
                $existingUser = $userModel->find($emailIdentity->user_id);
                if ($existingUser !== null) {
                    // Link this provider identity to the existing user
                    $newIdentity = new UserIdentity([
                        'user_id'      => $existingUser->id,
                        'type'         => $identityType,
                        'name'         => $socialUser->id,
                        'secret'       => $socialUser->accessToken,
                        'secret2'      => $socialUser->avatar,
                        'extra'        => json_encode($socialUser->raw),
                        'last_used_at' => date('Y-m-d H:i:s'),
                    ]);
                    $identityModel->insert($newIdentity);

                    Events::trigger('socialLinked', $existingUser, $provider, $socialUser);
                    Events::trigger('socialLogin', $existingUser, $provider, $socialUser);
                    return $existingUser;
                }
            }
        }

        // 3. Create a new User
        $username = $socialUser->username ?? ('user_' . bin2hex(random_bytes(4)));

        // Ensure username uniqueness
        $baseUsername = $username;
        $counter = 1;
        while ($userModel->where('username', $username)->first() !== null) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        $userEntityClass = config('Auth')->userEntity ?? User::class;
        $newUser = new $userEntityClass([
            'username'   => $username,
            'active'     => 1,
            'status'     => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $userId = $userModel->insert($newUser);
        $user = $userModel->find($userId);

        if ($user === null) {
            throw new RuntimeException('Failed to create new user record during social login.');
        }

        // 4. Attach Social Identity
        $extraPayload = array_merge($socialUser->raw, [
            'email' => $socialUser->email,
            'name'  => $socialUser->name,
        ]);

        $newIdentity = new UserIdentity([
            'user_id'      => $user->id,
            'type'         => $identityType,
            'name'         => $socialUser->id,
            'secret'       => $socialUser->accessToken,
            'secret2'      => $socialUser->avatar,
            'extra'        => json_encode($extraPayload),
            'last_used_at' => date('Y-m-d H:i:s'),
        ]);
        $identityModel->insert($newIdentity);

        Events::trigger('register', $user);
        Events::trigger('socialRegistered', $user, $provider, $socialUser);
        Events::trigger('socialLogin', $user, $provider, $socialUser);

        return $user;
    }
}
