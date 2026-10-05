<?php

declare(strict_types=1);

namespace Tests\Unit;

use Config\Services;
use Jengo\Auth\Entities\User;
use Jengo\Auth\Social\Contracts\SocialProviderInterface;
use Jengo\Auth\Social\DTOs\SocialUserDTO;
use Jengo\Auth\Social\SocialManager;
use Tests\TestCase;

class DummySocialProvider implements SocialProviderInterface
{
    public function __construct(protected array $config = []) {}

    public function getIdentifier(): string
    {
        return 'mock_provider';
    }

    public function getAuthUrl(array $options = []): string
    {
        return 'https://mock.example.com/oauth/auth?client_id=123';
    }

    public function handleCallback(array $queryParams): SocialUserDTO
    {
        return new SocialUserDTO(
            id: 'mock_sub_9999',
            email: 'mockuser@example.com',
            name: 'Mock User',
            avatar: 'https://example.com/avatar.png',
            username: 'mockuser',
            accessToken: 'mock_access_token_123',
            refreshToken: 'mock_refresh_token_123',
            raw: ['mock' => true]
        );
    }
}

class SocialAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        auth()->getUserIdentityModel()->emptyTable();
        auth()->getUserModel()->emptyTable();
    }

    public function testSocialManagerCreationAndExtensibility(): void
    {
        $social = new SocialManager([
            'enabled' => true,
            'providers' => [
                'google' => [
                    'enabled'       => true,
                    'client_id'     => 'google-id',
                    'client_secret' => 'google-secret',
                ],
            ],
        ]);

        $this->assertTrue($social->isEnabled());
        $this->assertTrue($social->hasProvider('google'));

        $driver = $social->driver('google');
        $this->assertSame('google', $driver->getIdentifier());
        $this->assertStringContainsString('https://accounts.google.com/o/oauth2/v2/auth', $driver->getAuthUrl());

        // Test custom provider extension
        $social->extend('custom', function ($config) {
            return new DummySocialProvider($config);
        });

        $this->assertTrue($social->hasProvider('custom'));
        $customDriver = $social->driver('custom');
        $this->assertSame('mock_provider', $customDriver->getIdentifier());
    }

    public function testSocialUserRegistrationAndIdentityCreation(): void
    {
        $social = new SocialManager();
        $social->extend('mock', function ($config) {
            return new DummySocialProvider($config);
        });

        // 1. First callback creates user and attaches social identity
        $user = $social->handleCallback('mock', ['code' => 'valid_code', 'state' => 'valid_state']);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('mockuser@example.com', $user->getEmail());
        $this->assertSame('mockuser', $user->getUsername());
        $this->assertTrue($user->hasSocialIdentity('mock'));
        $this->assertFalse($user->hasPassword()); // No password created yet

        // Check identity model record
        $identity = auth()->getUserIdentityModel()->where('user_id', $user->id)->first();
        $this->assertNotNull($identity);
        $this->assertSame('oauth_mock', $identity->type);
        $this->assertSame('mock_sub_9999', $identity->name);
        $this->assertSame('mock_access_token_123', $identity->secret);
        $this->assertSame('https://example.com/avatar.png', $identity->secret2);

        // 2. Setting password for OAuth registered user
        $user->setPassword('NewSecurePass123!');
        $this->assertTrue($user->hasPassword());

        $passwordIdentity = auth()->getUserIdentityModel()->where('user_id', $user->id)->where('type', 'email_password')->first();
        $this->assertNotNull($passwordIdentity);
        $this->assertTrue(auth()->getHasher()->verify('NewSecurePass123!', $passwordIdentity->secret));

        // 3. Second callback for same user returns existing user
        $reloadedUser = $social->handleCallback('mock', ['code' => 'another_code', 'state' => 'another_state']);
        $this->assertSame($user->id, $reloadedUser->id);
    }

    public function testAutoLinkingSocialAccountToExistingUserWithSameEmail(): void
    {
        // 1. Create traditional user with email & password
        $userModel = auth()->getUserModel();
        $user = new User([
            'username'   => 'jane_doe',
            'active'     => 1,
            'status'     => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $userId = $userModel->insert($user);
        $existingUser = $userModel->find($userId);
        $existingUser->setPassword('OldPassword123!', 'mockuser@example.com');

        $this->assertTrue($existingUser->hasPassword());
        $this->assertFalse($existingUser->hasSocialIdentity('mock'));

        // 2. User signs in with social provider returning same email
        $social = new SocialManager([
            'enabled'                  => true,
            'auto_link_verified_email' => true,
        ]);
        $social->extend('mock', function ($config) {
            return new DummySocialProvider($config);
        });

        $linkedUser = $social->handleCallback('mock', ['code' => 'xyz']);

        $this->assertSame($existingUser->id, $linkedUser->id);
        $this->assertTrue($linkedUser->hasSocialIdentity('mock'));
        $this->assertTrue($linkedUser->hasPassword()); // Preserves existing password
    }

    public function testRouteRegistrarSocialAndSetPassword(): void
    {
        \Jengo\Auth\Support\RouteRegistrar::resetGlobalOptions();
        $routes = Services::routes();
        $routes->resetRoutes();

        auth()->socialRoutes($routes);
        auth()->setPasswordRoutes($routes);

        $getRoutes = $routes->getRoutes('GET');
        $postRoutes = $routes->getRoutes('POST');

        $this->assertArrayHasKey('oauth/([^/]+)', $getRoutes);
        $this->assertArrayHasKey('oauth/callback/([^/]+)', $getRoutes);
        $this->assertArrayHasKey('set-password', $getRoutes);
        $this->assertArrayHasKey('set-password', $postRoutes);

        $this->assertSame('/oauth/google', $routes->reverseRoute('auth.oauth.redirect', 'google'));
        $this->assertSame('/oauth/callback/google', $routes->reverseRoute('auth.oauth.callback', 'google'));
        $this->assertSame('/set-password', $routes->reverseRoute('auth.password.set.view'));
    }

    public function testRouteRegistrarFlowIntrospection(): void
    {
        \Jengo\Auth\Support\RouteRegistrar::resetGlobalOptions();
        $routes = Services::routes();
        $routes->resetRoutes();

        auth()->coreRoutes($routes);

        $this->assertTrue(\Jengo\Auth\Support\RouteRegistrar::isFlowPublished('login'));
        $this->assertTrue(\Jengo\Auth\Support\RouteRegistrar::isFlowPublished('register'));
        $this->assertTrue(\Jengo\Auth\Support\RouteRegistrar::isFlowPublished('password-reset'));
        $this->assertFalse(\Jengo\Auth\Support\RouteRegistrar::isFlowPublished('magic-link'));
        $this->assertFalse(\Jengo\Auth\Support\RouteRegistrar::isFlowPublished('tokens'));

        // Publish magic link
        auth()->magicLinkRoutes($routes);
        $this->assertTrue(\Jengo\Auth\Support\RouteRegistrar::isFlowPublished('magic-link'));

        // Published flows summary
        $published = \Jengo\Auth\Support\RouteRegistrar::getPublishedFlows();
        $this->assertTrue($published['login']);
        $this->assertTrue($published['magic-link']);
    }

    public function testIdentityListingAndUnlinking(): void
    {
        // 1. Create a user with both a password and social identity
        $userModel = auth()->getUserModel();
        $user = new User([
            'username'   => 'link_user',
            'active'     => 1,
            'status'     => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $userId = $userModel->insert($user);
        $user = $userModel->find($userId);
        $user->setPassword('SecurePass123!', 'linkuser@example.com');

        $identityModel = auth()->getUserIdentityModel();
        $identityId = $identityModel->insert(new \Jengo\Auth\Entities\UserIdentity([
            'user_id'      => $user->id,
            'type'         => 'oauth_google',
            'name'         => 'google_12345',
            'secret'       => 'token_xyz',
            'created_at'   => date('Y-m-d H:i:s'),
        ]));

        $summary = $user->getIdentitiesSummary();
        $this->assertCount(1, $summary);
        $this->assertSame('google', $summary[0]['provider']);
        $this->assertSame('Google', $summary[0]['provider_name']);

        // Log user in
        auth()->login($user);
        $this->assertTrue(auth()->check());

        // 2. Controller show identities
        $controller = new \Jengo\Auth\Controllers\IdentityController();
        $controller->initController(Services::request(), Services::response(), Services::logger());
        $response = $controller->showIdentities();
        $this->assertSame(200, $response->getStatusCode());

        // 3. Controller unlink identity (returns 302 redirect for standard web view)
        $unlinkResponse = $controller->unlinkIdentity((string) $identityId);
        $this->assertSame(302, $unlinkResponse->getStatusCode());

        $this->assertNull($identityModel->find($identityId));

        // 4. Test protection against unlinking sole identity
        // Create user with ONLY an OAuth identity and no password
        $oauthOnlyUser = new User([
            'username'   => 'oauth_only',
            'active'     => 1,
            'status'     => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $oauthOnlyUserId = $userModel->insert($oauthOnlyUser);
        $oauthOnlyUser = $userModel->find($oauthOnlyUserId);

        $soleIdentityId = $identityModel->insert(new \Jengo\Auth\Entities\UserIdentity([
            'user_id'      => $oauthOnlyUser->id,
            'type'         => 'oauth_github',
            'name'         => 'github_99999',
            'secret'       => 'token_abc',
            'created_at'   => date('Y-m-d H:i:s'),
        ]));

        auth()->login($oauthOnlyUser);
        $this->assertFalse($oauthOnlyUser->hasPassword());

        $failedUnlink = $controller->unlinkIdentity((string) $soleIdentityId);
        $this->assertSame(302, $failedUnlink->getStatusCode());
        $this->assertNotNull($identityModel->find($soleIdentityId));
    }
}
