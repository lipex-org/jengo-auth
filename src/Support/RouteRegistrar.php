<?php

declare(strict_types=1);

namespace Jengo\Auth\Support;

use CodeIgniter\Router\RouteCollection;
use Jengo\Auth\Controllers\ActionController;
use Jengo\Auth\Controllers\ForgotPasswordController;
use Jengo\Auth\Controllers\LoginController;
use Jengo\Auth\Controllers\MagicLinkController;
use Jengo\Auth\Controllers\RegisterController;
use Jengo\Auth\Controllers\ResetPasswordController;
use Jengo\Auth\Controllers\TokenController;

class RouteRegistrar
{
    public static function routes(RouteCollection $routes, array $options = []): void
    {
        $except = $options['except'] ?? [];
        $config = config('Auth');

        // 1. Login & Logout
        if (! in_array('login', $except, true) && ($config->allowLogin ?? true)) {
            $routes->get('login', '\\' . LoginController::class . '::showLogin', ['as' => 'login']);
            $routes->post('login', '\\' . LoginController::class . '::attemptLogin', ['as' => 'login.attempt']);
            $routes->post('logout', '\\' . LoginController::class . '::logout', ['as' => 'logout']);
            $routes->get('logout', '\\' . LoginController::class . '::logout', ['as' => 'logout.get']);
        }

        // 2. Registration
        if (! in_array('register', $except, true) && ($config->allowRegistration ?? true)) {
            $routes->get('register', '\\' . RegisterController::class . '::showRegister', ['as' => 'register']);
            $routes->post('register', '\\' . RegisterController::class . '::attemptRegister', ['as' => 'register.attempt']);
        }

        // 3. Password Reset
        if (! in_array('password-reset', $except, true) && ($config->allowPasswordReset ?? true)) {
            $routes->get('forgot-password', '\\' . ForgotPasswordController::class . '::showForgot', ['as' => 'forgot-password']);
            $routes->post('forgot-password', '\\' . ForgotPasswordController::class . '::sendResetLink', ['as' => 'forgot-password.send']);
            $routes->get('reset-password/(:segment)', '\\' . ResetPasswordController::class . '::showReset/$1', ['as' => 'reset-password']);
            $routes->get('reset-password', '\\' . ResetPasswordController::class . '::showReset', ['as' => 'reset-password.query']);
            $routes->post('reset-password', '\\' . ResetPasswordController::class . '::attemptReset', ['as' => 'reset-password.attempt']);
        }

        // 4. Magic Link
        if (! in_array('magic-link', $except, true) && ($config->allowMagicLink ?? true)) {
            $routes->get('magic-link', '\\' . MagicLinkController::class . '::showMagicLink', ['as' => 'magic-link']);
            $routes->post('magic-link', '\\' . MagicLinkController::class . '::sendLink', ['as' => 'magic-link.send']);
            $routes->get('magic-link/verify/(:segment)', '\\' . MagicLinkController::class . '::verifyLink/$1', ['as' => 'magic-link.verify']);
            $routes->get('magic-link/verify', '\\' . MagicLinkController::class . '::verifyLink', ['as' => 'magic-link.verify.query']);
        }

        // 5. Auth Action / MFA
        if (! in_array('action', $except, true)) {
            $routes->get('auth/action/show', '\\' . ActionController::class . '::show', ['as' => 'auth.action.show']);
            $routes->post('auth/action/handle', '\\' . ActionController::class . '::handle', ['as' => 'auth.action.handle']);
        }

        // 6. Personal Access Tokens
        if (! in_array('tokens', $except, true) && ($config->allowTokens ?? true)) {
            $routes->group('api/tokens', static function ($routes) {
                $routes->get('/', '\\' . TokenController::class . '::index');
                $routes->post('/', '\\' . TokenController::class . '::create');
                $routes->delete('(:segment)', '\\' . TokenController::class . '::revoke/$1');
            });
        }
    }
}
