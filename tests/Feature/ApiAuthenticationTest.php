<?php

declare(strict_types=1);

namespace Tests\Feature;

use Config\Services;
use Jengo\Auth\Controllers\LoginController;
use Jengo\Auth\Controllers\RegisterController;
use Jengo\Auth\Controllers\TokenController;
use Jengo\Auth\Modifiers\JsonModifier;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    public function testRegisterAndLoginFlow(): void
    {
        config('Auth')->responseModifier = JsonModifier::class;
        $request = Services::request();

        // 1. Register via JSON payload
        $request->setBody(json_encode([
            'username'         => 'alice',
            'email'            => 'alice@example.com',
            'password'         => 'Secret12345!',
            'password_confirm' => 'Secret12345!',
        ]));

        $registerController = new RegisterController();
        $registerController->initController($request, Services::response(), Services::logger());
        $response = $registerController->attemptRegister();
        $this->assertSame(201, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertSame('success', $data['status']);
        $this->assertSame('alice', $data['user']['username']);

        // 2. Issue Personal Access Token
        $user = auth()->user();
        $tokenResult = auth()->createTokenFor($user, 'API Token');
        $this->assertNotEmpty($tokenResult->plainTextToken);

        // 3. Logout
        $loginController = new LoginController();
        $loginController->initController($request, Services::response(), Services::logger());
        $logoutResponse = $loginController->logout();
        $this->assertSame(200, $logoutResponse->getStatusCode());

        // 4. Login via POST payload
        $request->setBody('');
        $_POST = [
            'email'    => 'alice@example.com',
            'password' => 'Secret12345!',
        ];

        $loginResponse = $loginController->attemptLogin();
        $this->assertSame(200, $loginResponse->getStatusCode());

        $loginData = json_decode($loginResponse->getBody(), true);
        $this->assertSame('success', $loginData['status']);
        $this->assertSame('alice', $loginData['user']['username']);

        // 5. Token listing
        $tokenController = new TokenController();
        $tokenController->initController($request, Services::response(), Services::logger());
        $tokenListResponse = $tokenController->index();
        $this->assertSame(200, $tokenListResponse->getStatusCode());

        $_POST = [];
    }
}
