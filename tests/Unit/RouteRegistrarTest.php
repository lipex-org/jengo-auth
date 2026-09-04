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
}
