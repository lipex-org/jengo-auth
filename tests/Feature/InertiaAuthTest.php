<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\HTTP\RedirectResponse;
use Config\Services;
use Jengo\Auth\Controllers\ForgotPasswordController;
use Jengo\Auth\Controllers\LoginController;
use Jengo\Auth\Controllers\MagicLinkController;
use Jengo\Auth\Controllers\RegisterController;
use Jengo\Auth\Modifiers\InertiaModifier;
use Tests\TestCase;

class InertiaAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config('Auth')->responseModifier = InertiaModifier::class;
    }

    public function testShowLoginDeliversSpecCompliantInertiaJsonResponse(): void
    {
        $request = Services::request();
        $request->setHeader('X-Inertia', 'true');
        $request->setHeader('X-Inertia-Version', '1.0');

        $controller = new LoginController();
        $controller->initController($request, Services::response(), Services::logger());

        $response = $controller->showLogin();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('X-Inertia'));
        $this->assertSame('true', $response->getHeaderLine('X-Inertia'));
        $this->assertSame('X-Inertia', $response->getHeaderLine('Vary'));

        $data = json_decode($response->getBody(), true);
        $this->assertSame('Auth/Login', $data['component']);
        $this->assertArrayHasKey('props', $data);
        $this->assertArrayHasKey('errors', $data['props']);
    }

    public function testShowRegisterDeliversSpecCompliantInertiaJsonResponse(): void
    {
        $request = Services::request();
        $request->setHeader('X-Inertia', 'true');

        $controller = new RegisterController();
        $controller->initController($request, Services::response(), Services::logger());

        $response = $controller->showRegister();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('X-Inertia'));

        $data = json_decode($response->getBody(), true);
        $this->assertSame('Auth/Register', $data['component']);
        $this->assertArrayHasKey('props', $data);
    }

    public function testShowForgotPasswordDeliversSpecCompliantInertiaJsonResponse(): void
    {
        $request = Services::request();
        $request->setHeader('X-Inertia', 'true');

        $controller = new ForgotPasswordController();
        $controller->initController($request, Services::response(), Services::logger());

        $response = $controller->showForgot();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('X-Inertia'));

        $data = json_decode($response->getBody(), true);
        $this->assertSame('Auth/ForgotPassword', $data['component']);
    }

    public function testShowMagicLinkDeliversSpecCompliantInertiaJsonResponse(): void
    {
        $request = Services::request();
        $request->setHeader('X-Inertia', 'true');

        $controller = new MagicLinkController();
        $controller->initController($request, Services::response(), Services::logger());

        $response = $controller->showMagicLink();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('X-Inertia'));

        $data = json_decode($response->getBody(), true);
        $this->assertSame('Auth/MagicLink', $data['component']);
    }

    public function testInertiaValidationFailureFlashesErrorsAndRedirectsBack(): void
    {
        $modifier = new InertiaModifier();
        $request = Services::request();
        $errors = ['email' => 'The email field is required.'];

        $response = $modifier->modifyValidationFailed($errors, $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($errors, Services::session()->getFlashdata('errors'));
    }
}
