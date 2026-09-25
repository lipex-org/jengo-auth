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

    public function testShowLoginThrowsExceptionWhenInertiaNotInstalled(): void
    {
        $request = Services::request();
        $request->setHeader('X-Inertia', 'true');
        $request->setHeader('X-Inertia-Version', '1.0');

        $controller = new LoginController();
        $controller->initController($request, Services::response(), Services::logger());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The jengo/inertia package is required to use InertiaModifier. Run: composer require jengo/inertia');

        $controller->showLogin();
    }

    public function testShowRegisterThrowsExceptionWhenInertiaNotInstalled(): void
    {
        $request = Services::request();
        $request->setHeader('X-Inertia', 'true');

        $controller = new RegisterController();
        $controller->initController($request, Services::response(), Services::logger());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The jengo/inertia package is required to use InertiaModifier. Run: composer require jengo/inertia');

        $controller->showRegister();
    }

    public function testShowForgotPasswordThrowsExceptionWhenInertiaNotInstalled(): void
    {
        $request = Services::request();
        $request->setHeader('X-Inertia', 'true');

        $controller = new ForgotPasswordController();
        $controller->initController($request, Services::response(), Services::logger());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The jengo/inertia package is required to use InertiaModifier. Run: composer require jengo/inertia');

        $controller->showForgot();
    }

    public function testShowMagicLinkThrowsExceptionWhenInertiaNotInstalled(): void
    {
        $request = Services::request();
        $request->setHeader('X-Inertia', 'true');

        $controller = new MagicLinkController();
        $controller->initController($request, Services::response(), Services::logger());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The jengo/inertia package is required to use InertiaModifier. Run: composer require jengo/inertia');

        $controller->showMagicLink();
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
