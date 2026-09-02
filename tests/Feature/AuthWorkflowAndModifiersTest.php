<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Events\Events;
use Config\Services;
use Jengo\Auth\Actions\Email2FA;
use Jengo\Auth\Controllers\ActionController;
use Jengo\Auth\Controllers\ForgotPasswordController;
use Jengo\Auth\Controllers\LoginController;
use Jengo\Auth\Controllers\MagicLinkController;
use Jengo\Auth\Controllers\RegisterController;
use Jengo\Auth\Controllers\ResetPasswordController;
use Jengo\Auth\Modifiers\JsonModifier;
use Jengo\Auth\Modifiers\StandardViewModifier;
use Tests\TestCase;

class AuthWorkflowAndModifiersTest extends TestCase
{
    public function testRoutePublishing(): void
    {
        $routes = Services::routes();
        auth()->routes($routes);

        $registeredRoutes = $routes->getRoutes('GET');
        $this->assertArrayHasKey('login', $registeredRoutes);
        $this->assertArrayHasKey('register', $registeredRoutes);
        $this->assertArrayHasKey('forgot-password', $registeredRoutes);
        $this->assertArrayHasKey('magic-link', $registeredRoutes);
        $this->assertArrayHasKey('auth/action/show', $registeredRoutes);
    }

    public function testRegistrationAndLoginFlowWithEvents(): void
    {
        $eventsFired = [];
        Events::on('register', static function ($user) use (&$eventsFired) {
            $eventsFired[] = 'register:' . $user->username;
        });
        Events::on('login', static function ($user) use (&$eventsFired) {
            $eventsFired[] = 'login:' . $user->username;
        });
        Events::on('logout', static function ($user) use (&$eventsFired) {
            $eventsFired[] = 'logout:' . ($user ? $user->username : 'none');
        });

        // 1. Register with JSON payload
        config('Auth')->responseModifier = JsonModifier::class;

        $request = Services::request();
        $request->setBody(json_encode([
            'username'         => 'alice_events',
            'email'            => 'alice_events@example.com',
            'password'         => 'Secret1234!',
            'password_confirm' => 'Secret1234!',
        ]));

        $registerController = new RegisterController();
        $registerController->initController($request, Services::response(), Services::logger());
        $regResponse = $registerController->attemptRegister();

        $this->assertSame(201, $regResponse->getStatusCode());
        $regData = json_decode($regResponse->getBody(), true);
        $this->assertSame('success', $regData['status']);
        $this->assertContains('register:alice_events', $eventsFired);

        // 2. Logout
        $loginController = new LoginController();
        $loginController->initController($request, Services::response(), Services::logger());
        $logoutResponse = $loginController->logout();
        $this->assertSame(200, $logoutResponse->getStatusCode());
        $this->assertContains('logout:alice_events', $eventsFired);

        // 3. Login with standard POST payload
        $request->setBody('');
        $_POST = [
            'email'    => 'alice_events@example.com',
            'password' => 'Secret1234!',
        ];

        $loginResponse = $loginController->attemptLogin();
        $this->assertSame(200, $loginResponse->getStatusCode());
        $loginData = json_decode($loginResponse->getBody(), true);
        $this->assertSame('success', $loginData['status']);
        $this->assertContains('login:alice_events', $eventsFired);

        $_POST = [];
    }

    public function testFeatureFlagDisablesRegistrationAndReturns404(): void
    {
        config('Auth')->allowRegistration = false;
        config('Auth')->responseModifier = JsonModifier::class;

        $request = Services::request();
        $registerController = new RegisterController();
        $registerController->initController($request, Services::response(), Services::logger());

        $response = $registerController->showRegister();
        $this->assertSame(404, $response->getStatusCode());

        $attemptResponse = $registerController->attemptRegister();
        $this->assertSame(404, $attemptResponse->getStatusCode());

        config('Auth')->allowRegistration = true;
    }

    public function testPostLoginMfaActionPipelineAndStrict404OnFailure(): void
    {
        // 1. Create User
        $request = Services::request();
        $request->setBody(json_encode([
            'username'         => 'bob',
            'email'            => 'bob@example.com',
            'password'         => 'Secret1234!',
            'password_confirm' => 'Secret1234!',
        ]));

        config('Auth')->responseModifier = JsonModifier::class;
        $registerController = new RegisterController();
        $registerController->initController($request, Services::response(), Services::logger());
        $registerController->attemptRegister();

        // 2. Configure Post-Login MFA Action
        config('Auth')->actions['login'] = Email2FA::class;

        auth()->logout();

        // 3. Attempt login -> Triggers action_required
        $request->setBody(json_encode([
            'email'    => 'bob@example.com',
            'password' => 'Secret1234!',
        ]));

        $loginController = new LoginController();
        $loginController->initController($request, Services::response(), Services::logger());
        $loginResponse = $loginController->attemptLogin();

        $this->assertSame(200, $loginResponse->getStatusCode());
        $loginData = json_decode($loginResponse->getBody(), true);
        $this->assertSame('login.action_required', $loginData['action']);

        // User should not be fully authenticated yet
        $this->assertFalse(auth()->check());

        // 4. Action Show
        $actionController = new ActionController();
        $actionController->initController($request, Services::response(), Services::logger());
        $showResponse = $actionController->show();
        $this->assertSame(200, $showResponse->getStatusCode());

        // 5. Invalid MFA verification -> MUST return 404
        $request->setBody(json_encode(['code' => '000000']));
        $invalidResponse = $actionController->handle();
        $this->assertSame(404, $invalidResponse->getStatusCode());

        // 6. Valid MFA verification -> 200 and logged in
        $validCode = Services::session()->get('mfa_code');
        $this->assertNotEmpty($validCode);

        $request->setBody(json_encode(['code' => $validCode]));
        $validResponse = $actionController->handle();
        $this->assertSame(200, $validResponse->getStatusCode());

        $this->assertTrue(auth()->check());
        $this->assertSame('bob', auth()->user()->username);

        config('Auth')->actions['login'] = null;
    }

    public function testMagicLinkFlow(): void
    {
        config('Auth')->responseModifier = JsonModifier::class;

        // Register user
        $request = Services::request();
        $request->setBody(json_encode([
            'username'         => 'carol',
            'email'            => 'carol@example.com',
            'password'         => 'Secret1234!',
            'password_confirm' => 'Secret1234!',
        ]));
        $registerController = new RegisterController();
        $registerController->initController($request, Services::response(), Services::logger());
        $registerController->attemptRegister();
        auth()->logout();

        $magicToken = null;
        Events::on('magicLink', static function ($user, $token) use (&$magicToken) {
            $magicToken = $token;
        });

        // Send magic link
        $magicController = new MagicLinkController();
        $request->setBody(json_encode(['email' => 'carol@example.com']));
        $magicController->initController($request, Services::response(), Services::logger());
        $sendResponse = $magicController->sendLink();

        $this->assertSame(200, $sendResponse->getStatusCode());
        $this->assertNotNull($magicToken);

        // Verify with invalid token -> 404
        $invalidResponse = $magicController->verifyLink('invalid-token-123');
        $this->assertSame(404, $invalidResponse->getStatusCode());

        // Verify with valid token -> 200 & logged in
        $validResponse = $magicController->verifyLink($magicToken);
        $this->assertSame(200, $validResponse->getStatusCode());
        $this->assertTrue(auth()->check());
        $this->assertSame('carol', auth()->user()->username);
    }

    public function testInertiaModifierUsesConfiguredViews(): void
    {
        config('Auth')->responseModifier = \Jengo\Auth\Modifiers\InertiaModifier::class;
        config('Auth')->views['login'] = 'Pages/Auth/CustomLogin';

        $request = Services::request();
        $loginController = new LoginController();
        $loginController->initController($request, Services::response(), Services::logger());

        $response = $loginController->showLogin();
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertSame('Pages/Auth/CustomLogin', $data['component']);

        // Reset config
        config('Auth')->views['login'] = 'Jengo\Auth\Views\login';
        config('Auth')->responseModifier = \Jengo\Auth\Modifiers\StandardViewModifier::class;
    }

    public function testFeatureFlagDisablesLoginAndReturns404(): void
    {
        config('Auth')->allowLogin = false;
        config('Auth')->responseModifier = JsonModifier::class;

        $request = Services::request();
        $loginController = new LoginController();
        $loginController->initController($request, Services::response(), Services::logger());

        $response = $loginController->showLogin();
        $this->assertSame(404, $response->getStatusCode());

        $attemptResponse = $loginController->attemptLogin();
        $this->assertSame(404, $attemptResponse->getStatusCode());

        config('Auth')->allowLogin = true;
    }
}
