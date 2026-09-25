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

    public function testInertiaModifierThrowsExceptionWhenInertiaNotInstalled(): void
    {
        config('Auth')->responseModifier = \Jengo\Auth\Modifiers\InertiaModifier::class;
        config('Auth')->views['login'] = 'Pages/Auth/CustomLogin';

        $request = Services::request();
        $request->setHeader('X-Inertia', 'true');
        $loginController = new LoginController();
        $loginController->initController($request, Services::response(), Services::logger());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The jengo/inertia package is required to use InertiaModifier. Run: composer require jengo/inertia');

        $loginController->showLogin();

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

    public function testMultiActionPipelineSequentialExecution(): void
    {
        // 1. Register a user
        $request = Services::request();
        $request->setBody(json_encode([
            'username'         => 'david_pipeline',
            'email'            => 'david@example.com',
            'password'         => 'Secret1234!',
            'password_confirm' => 'Secret1234!',
        ]));

        config('Auth')->responseModifier = JsonModifier::class;
        $registerController = new RegisterController();
        $registerController->initController($request, Services::response(), Services::logger());
        $registerController->attemptRegister();

        // 2. Define a secondary dummy action
        $dummyActionClass = new class implements \Jengo\Auth\Contracts\AuthActionInterface {
            public function getActionName(): string
            {
                return 'terms';
            }

            public function show(\CodeIgniter\HTTP\RequestInterface $request, \Jengo\Auth\Entities\User $user): \CodeIgniter\HTTP\ResponseInterface
            {
                return Services::response()->setStatusCode(200)->setJSON(['action' => 'terms.view']);
            }

            public function verify(\CodeIgniter\HTTP\RequestInterface $request, \Jengo\Auth\Entities\User $user): bool
            {
                $data = json_decode((string) $request->getBody(), true) ?? [];
                return ! empty($data['accept_terms']);
            }
        };

        // 3. Configure sequential array pipeline for login
        config('Auth')->actions['login'] = [
            Email2FA::class,
            get_class($dummyActionClass),
        ];

        auth()->logout();

        // 4. Attempt login -> Triggers action_required
        $request->setBody(json_encode([
            'email'    => 'david@example.com',
            'password' => 'Secret1234!',
        ]));

        $loginController = new LoginController();
        $loginController->initController($request, Services::response(), Services::logger());
        $loginResponse = $loginController->attemptLogin();

        $this->assertSame(200, $loginResponse->getStatusCode());
        $loginData = json_decode($loginResponse->getBody(), true);
        $this->assertSame('login.action_required', $loginData['action']);
        $this->assertFalse(auth()->check());

        // 5. Action 1: Email2FA challenge
        $actionController = new ActionController();
        $actionController->initController($request, Services::response(), Services::logger());
        $showResponse = $actionController->show();
        $this->assertSame(200, $showResponse->getStatusCode());

        $validCode = Services::session()->get('mfa_code');
        $this->assertNotEmpty($validCode);

        // Verify Action 1 -> Returns action.next because Action 2 is pending
        $request->setBody(json_encode(['code' => $validCode]));
        $step1Response = $actionController->handle();
        $this->assertSame(200, $step1Response->getStatusCode());
        $step1Data = json_decode($step1Response->getBody(), true);
        $this->assertSame('action.next', $step1Data['action']);
        $this->assertFalse(auth()->check());

        // 6. Action 2: DummyTerms challenge
        $show2Response = $actionController->show();
        $this->assertSame(200, $show2Response->getStatusCode());
        $show2Data = json_decode($show2Response->getBody(), true);
        $this->assertSame('terms.view', $show2Data['action']);

        // Invalid Action 2 verification -> 404
        $request->setBody(json_encode(['accept_terms' => false]));
        $invalid2Response = $actionController->handle();
        $this->assertSame(404, $invalid2Response->getStatusCode());
        $this->assertFalse(auth()->check());

        // Valid Action 2 verification -> action.success and authenticated
        $request->setBody(json_encode(['accept_terms' => true]));
        $valid2Response = $actionController->handle();
        $this->assertSame(200, $valid2Response->getStatusCode());
        $valid2Data = json_decode($valid2Response->getBody(), true);
        $this->assertSame('action.success', $valid2Data['action']);

        $this->assertTrue(auth()->check());
        $this->assertSame('david_pipeline', auth()->user()->username);

        config('Auth')->actions['login'] = null;
    }

    public function testEmailActivatorPostRegistrationFlow(): void
    {
        // 1. Configure EmailActivator for register
        config('Auth')->actions['register'] = \Jengo\Auth\Actions\EmailActivator::class;
        config('Auth')->responseModifier = JsonModifier::class;

        $request = Services::request();
        $request->setBody(json_encode([
            'username'         => 'frank_activator',
            'email'            => 'frank_activator@example.com',
            'password'         => 'Secret1234!',
            'password_confirm' => 'Secret1234!',
        ]));

        $registerController = new RegisterController();
        $registerController->initController($request, Services::response(), Services::logger());
        $regResponse = $registerController->attemptRegister();

        $this->assertSame(200, $regResponse->getStatusCode());
        $regData = json_decode($regResponse->getBody(), true);
        $this->assertSame('register.action_required', $regData['action']);

        // User is not yet authenticated
        $this->assertFalse(auth()->check());

        // 2. Show activation challenge
        $actionController = new ActionController();
        $actionController->initController($request, Services::response(), Services::logger());
        $showResponse = $actionController->show();
        $this->assertSame(200, $showResponse->getStatusCode());

        $activationCode = Services::session()->get('activation_code');
        $this->assertNotEmpty($activationCode);

        // 3. Invalid code -> 404
        $request->setBody(json_encode(['code' => '000000']));
        $invalidResponse = $actionController->handle();
        $this->assertSame(404, $invalidResponse->getStatusCode());
        $this->assertFalse(auth()->check());

        // 4. Valid code -> 200, activated and logged in
        $request->setBody(json_encode(['code' => $activationCode]));
        $validResponse = $actionController->handle();
        $this->assertSame(200, $validResponse->getStatusCode());
        $validData = json_decode($validResponse->getBody(), true);
        $this->assertSame('action.success', $validData['action']);

        $this->assertTrue(auth()->check());
        $this->assertSame('frank_activator', auth()->user()->username);

        config('Auth')->actions['register'] = null;
    }
}
