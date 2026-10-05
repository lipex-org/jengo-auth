<?php

declare(strict_types=1);

namespace Tests\Unit;

use Config\Services;
use Jengo\Auth\Controllers\LoginController;
use Tests\TestCase;

class CustomLoginController extends LoginController {}

class RouteRegistrarTest extends TestCase
{
    public function testDefaultRoutesRegistration(): void
    {
        $routes = Services::routes();
        $routes->resetRoutes();

        auth()->routes($routes);

        $registered = $routes->getRoutes('GET');
        $this->assertArrayHasKey('login', $registered);
        $this->assertArrayHasKey('register', $registered);
        $this->assertArrayHasKey('forgot-password', $registered);
        $this->assertArrayHasKey('magic-link', $registered);

        // Logout is POST by default (single endpoint)
        $this->assertArrayNotHasKey('logout', $registered);
        $this->assertArrayHasKey('logout', $routes->getRoutes('POST'));
    }

    public function testGroupPrefixOption(): void
    {
        $routes = Services::routes();
        $routes->resetRoutes();

        auth()->routes($routes, [
            'prefix' => 'auth/v1',
        ]);

        $registered = $routes->getRoutes('GET');
        $this->assertArrayHasKey('auth/v1/login', $registered);
        $this->assertArrayHasKey('auth/v1/register', $registered);
        $this->assertArrayHasKey('auth/v1/magic-link', $registered);
    }

    public function testCustomPathSlugs(): void
    {
        $routes = Services::routes();
        $routes->resetRoutes();

        auth()->routes($routes, [
            'paths' => [
                'login'    => 'sign-in',
                'logout'   => 'sign-out',
                'register' => 'join-us',
            ],
        ]);

        $registeredGet = $routes->getRoutes('GET');
        $registeredPost = $routes->getRoutes('POST');

        $this->assertArrayHasKey('sign-in', $registeredGet);
        $this->assertArrayHasKey('sign-in', $registeredPost);
        $this->assertArrayNotHasKey('sign-out', $registeredGet);
        $this->assertArrayHasKey('sign-out', $registeredPost);
        $this->assertArrayHasKey('join-us', $registeredGet);
    }

    public function testOnlyOption(): void
    {
        $routes = Services::routes();
        $routes->resetRoutes();

        auth()->routes($routes, [
            'only' => ['login', 'register'],
        ]);

        $registered = $routes->getRoutes('GET');
        $this->assertArrayHasKey('login', $registered);
        $this->assertArrayHasKey('register', $registered);
        $this->assertArrayNotHasKey('forgot-password', $registered);
        $this->assertArrayNotHasKey('magic-link', $registered);
    }

    public function testExceptOption(): void
    {
        $routes = Services::routes();
        $routes->resetRoutes();

        auth()->routes($routes, [
            'except' => ['magic-link', 'tokens'],
        ]);

        $registered = $routes->getRoutes('GET');
        $this->assertArrayHasKey('login', $registered);
        $this->assertArrayHasKey('register', $registered);
        $this->assertArrayNotHasKey('magic-link', $registered);
    }

    public function testControllerOverrides(): void
    {
        $routes = Services::routes();
        $routes->resetRoutes();

        auth()->routes($routes, [
            'controllers' => [
                'login' => CustomLoginController::class,
            ],
        ]);

        $registered = $routes->getRoutes('GET');
        $this->assertArrayHasKey('login', $registered);
        $this->assertStringContainsString('CustomLoginController::showLogin', $registered['login']);
    }

    public function testCanonicalNamedRoutesResolutionWithUrlTo(): void
    {
        $routes = Services::routes();
        $routes->resetRoutes();

        auth()->routes($routes, [
            'prefix' => 'auth',
            'paths'  => ['login' => 'signin', 'register' => 'signup'],
        ]);

        $this->assertSame('/auth/signin', $routes->reverseRoute('login'));
        $this->assertSame('/auth/signup', $routes->reverseRoute('register'));
        $this->assertSame('/auth/forgot-password', $routes->reverseRoute('forgot-password'));
        $this->assertSame('/auth/reset-password/abc123token', $routes->reverseRoute('reset-password', 'abc123token'));
        $this->assertSame('/auth/magic-link/verify/xyz987token', $routes->reverseRoute('magic-link.verify', 'xyz987token'));
        $this->assertSame('/auth/auth/action/show', $routes->reverseRoute('auth.action.show'));
    }

    public function testRouteOptionsTracking(): void
    {
        $routes = Services::routes();
        $routes->resetRoutes();

        auth()->routes($routes, [
            'prefix' => 'portal',
            'paths'  => ['login' => 'sign-in'],
        ]);

        $options = \Jengo\Auth\Support\RouteRegistrar::getOptions();
        $this->assertSame('portal', $options['prefix']);
        $this->assertSame('sign-in', $options['paths']['login']);
    }

    public function testSingleLogoutMethodGet(): void
    {
        $routes = Services::routes();
        $routes->resetRoutes();

        auth()->routes($routes, [
            'logoutMethod' => 'get',
        ]);

        $registeredGet = $routes->getRoutes('GET');
        $registeredPost = $routes->getRoutes('POST');

        $this->assertArrayHasKey('logout', $registeredGet);
        $this->assertArrayNotHasKey('logout', $registeredPost);
    }

    public function testCustomSudoAndTwoFactorRoutePaths(): void
    {
        $routes = Services::routes();
        $routes->resetRoutes();

        auth()->routes($routes, [
            'paths' => [
                'sudo'       => 'security/step-up',
                'two-factor' => 'account/mfa-settings',
            ],
        ]);

        $registeredGet = $routes->getRoutes('GET');
        $registeredPost = $routes->getRoutes('POST');

        $this->assertArrayHasKey('security/step-up', $registeredGet);
        $this->assertArrayHasKey('security/step-up/challenge', $registeredPost);
        $this->assertArrayHasKey('security/step-up/verify', $registeredPost);
        $this->assertArrayHasKey('security/step-up/exit', $registeredPost);

        $this->assertArrayHasKey('account/mfa-settings', $registeredGet);
        $this->assertArrayHasKey('account/mfa-settings/enroll/start', $registeredPost);
        $this->assertArrayHasKey('account/mfa-settings/enroll/confirm', $registeredPost);
        $this->assertArrayHasKey('account/mfa-settings/unenroll', $registeredPost);

        $this->assertSame('/security/step-up', $routes->reverseRoute('auth.sudo'));
        $this->assertSame('/account/mfa-settings', $routes->reverseRoute('two-factor.index'));
    }

    public function testDedicatedFeatureRouteHelpers(): void
    {
        $routes = Services::routes();

        // 1. Core routes helper
        $routes->resetRoutes();
        \Jengo\Auth\Support\RouteRegistrar::core($routes);
        $getRoutes = $routes->getRoutes('GET');
        $this->assertArrayHasKey('login', $getRoutes);
        $this->assertArrayHasKey('register', $getRoutes);
        $this->assertArrayHasKey('forgot-password', $getRoutes);
        $this->assertArrayNotHasKey('magic-link', $getRoutes);
        $this->assertArrayNotHasKey('user/two-factor', $getRoutes);

        // 2. Magic Link helper
        $routes->resetRoutes();
        \Jengo\Auth\Support\RouteRegistrar::magicLink($routes);
        $getRoutes = $routes->getRoutes('GET');
        $this->assertArrayHasKey('magic-link', $getRoutes);
        $this->assertArrayNotHasKey('login', $getRoutes);

        // 3. Action / MFA pipeline helper
        $routes->resetRoutes();
        \Jengo\Auth\Support\RouteRegistrar::action($routes);
        $getRoutes = $routes->getRoutes('GET');
        $postRoutes = $routes->getRoutes('POST');
        $this->assertArrayHasKey('auth/action/show', $getRoutes);
        $this->assertArrayHasKey('auth/action/challenge', $postRoutes);
        $this->assertArrayHasKey('auth/action/handle', $postRoutes);
        $this->assertArrayHasKey('auth/action/cancel', $postRoutes);
        $this->assertArrayNotHasKey('login', $getRoutes);

        // 4. Sudo Mode helper
        $routes->resetRoutes();
        \Jengo\Auth\Support\RouteRegistrar::sudo($routes);
        $getRoutes = $routes->getRoutes('GET');
        $postRoutes = $routes->getRoutes('POST');
        $this->assertArrayHasKey('auth/sudo', $getRoutes);
        $this->assertArrayHasKey('auth/sudo/challenge', $postRoutes);
        $this->assertArrayHasKey('auth/sudo/verify', $postRoutes);
        $this->assertArrayHasKey('auth/sudo/exit', $postRoutes);
        $this->assertArrayNotHasKey('login', $getRoutes);

        // 5. Two-Factor helper
        $routes->resetRoutes();
        \Jengo\Auth\Support\RouteRegistrar::twoFactor($routes);
        $getRoutes = $routes->getRoutes('GET');
        $postRoutes = $routes->getRoutes('POST');
        $this->assertArrayHasKey('user/two-factor', $getRoutes);
        $this->assertArrayHasKey('user/two-factor/enroll/start', $postRoutes);
        $this->assertArrayHasKey('user/two-factor/enroll/confirm', $postRoutes);
        $this->assertArrayNotHasKey('login', $getRoutes);

        // 6. Tokens helper
        $routes->resetRoutes();
        \Jengo\Auth\Support\RouteRegistrar::tokens($routes);
        $getRoutes = $routes->getRoutes('GET');
        $postRoutes = $routes->getRoutes('POST');
        $this->assertArrayHasKey('tokens', $getRoutes);
        $this->assertArrayHasKey('tokens', $postRoutes);
        $this->assertArrayHasKey('tokens/create', $postRoutes);
        $this->assertArrayNotHasKey('login', $getRoutes);
    }
}

