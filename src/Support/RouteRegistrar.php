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
    /**
     * Stores the last active route registration options for introspection.
     */
    protected static array $lastOptions = [];

    /**
     * Get the options passed to the route registrar.
     */
    public static function getOptions(): array
    {
        return static::$lastOptions;
    }

    /**
     * Publish authentication routes to a CodeIgniter RouteCollection with full developer customization.
     * Route labels ('as') are standardized and immutable to ensure reliable resolution via url_to().
     *
     * Supported options:
     * - 'only': array of flow names to include (e.g. ['login', 'register'])
     * - 'except': array of flow names to exclude (e.g. ['tokens', 'magic-link'])
     * - 'prefix' / 'group': URL prefix to group all auth routes under (e.g. 'auth')
     * - 'groupOptions': array of CI4 route group options (e.g. ['filter' => '...'])
     * - 'paths': array of endpoint slug overrides (e.g. ['login' => 'sign-in', 'register' => 'sign-up'])
     * - 'controllers': array of custom controller class overrides (e.g. ['login' => CustomLogin::class])
     * - 'filters': route-specific filters (e.g. ['register' => ['honeypot']])
     * - 'allowGetLogout': bool (enables/disables GET method for logout)
     */
    public static function routes(RouteCollection $routes, array $options = []): void
    {
        static::$lastOptions = $options;

        $config = config('Auth');
        $prefix = (string) ($options['prefix'] ?? $options['group'] ?? $config->routePrefix ?? '');
        $prefix = trim($prefix, '/');

        if ($prefix !== '') {
            $groupOptions = $options['groupOptions'] ?? [];
            if (isset($options['filter']) && ! isset($groupOptions['filter'])) {
                $groupOptions['filter'] = $options['filter'];
            }

            if (! empty($groupOptions)) {
                $routes->group($prefix, $groupOptions, static function (RouteCollection $groupedRoutes) use ($options, $config) {
                    static::registerDefinitions($groupedRoutes, $options, $config);
                });
            } else {
                $routes->group($prefix, static function (RouteCollection $groupedRoutes) use ($options, $config) {
                    static::registerDefinitions($groupedRoutes, $options, $config);
                });
            }
        } else {
            static::registerDefinitions($routes, $options, $config);
        }
    }

    /**
     * Register individual route definitions with fixed canonical labels ('as').
     */
    protected static function registerDefinitions(RouteCollection $routes, array $options, mixed $config): void
    {
        $only = (array) ($options['only'] ?? []);
        $except = (array) ($options['except'] ?? []);

        // Merge paths and controllers
        $defaultPaths = [
            'login'           => 'login',
            'logout'          => 'logout',
            'register'        => 'register',
            'forgot-password' => 'forgot-password',
            'reset-password'  => 'reset-password',
            'magic-link'      => 'magic-link',
            'action'          => 'auth/action',
            'tokens'          => 'api/tokens',
        ];
        $configPaths = (array) ($config->routePaths ?? []);
        $optionPaths = (array) ($options['paths'] ?? []);
        $paths = array_merge($defaultPaths, $configPaths, $optionPaths);

        $defaultControllers = [
            'login'           => LoginController::class,
            'register'        => RegisterController::class,
            'forgot-password' => ForgotPasswordController::class,
            'reset-password'  => ResetPasswordController::class,
            'magic-link'      => MagicLinkController::class,
            'action'          => ActionController::class,
            'tokens'          => TokenController::class,
        ];
        $configControllers = (array) ($config->routeControllers ?? []);
        $optionControllers = (array) ($options['controllers'] ?? []);
        $controllers = array_merge($defaultControllers, $configControllers, $optionControllers);

        $filters = (array) ($options['filters'] ?? []);
        $logoutMethod = strtolower((string) ($options['logoutMethod'] ?? ($options['allowGetLogout'] ?? false ? 'get' : null) ?? $config->logoutMethod ?? 'post'));

        // 1. Login & Logout
        if (static::isFlowEnabled('login', $only, $except, (bool) ($config->allowLogin ?? true))) {
            $loginCtrl = $controllers['login'] ?? LoginController::class;
            $pathLogin = trim((string) ($paths['login'] ?? 'login'), '/');
            $pathLogout = trim((string) ($paths['logout'] ?? 'logout'), '/');

            $loginOpts = ['as' => 'login'];
            if (isset($filters['login'])) {
                $loginOpts['filter'] = $filters['login'];
            }

            $attemptOpts = ['as' => 'login.attempt'];
            if (isset($filters['login'])) {
                $attemptOpts['filter'] = $filters['login'];
            }

            $logoutOpts = ['as' => 'logout'];
            if (isset($filters['logout'])) {
                $logoutOpts['filter'] = $filters['logout'];
            }

            $routes->get($pathLogin, [$loginCtrl, 'showLogin'], $loginOpts);
            $routes->post($pathLogin, [$loginCtrl, 'attemptLogin'], $attemptOpts);

            if ($logoutMethod === 'get') {
                $routes->get($pathLogout, [$loginCtrl, 'logout'], $logoutOpts);
            } else {
                $routes->post($pathLogout, [$loginCtrl, 'logout'], $logoutOpts);
            }
        }

        // 2. Registration
        if (static::isFlowEnabled('register', $only, $except, (bool) ($config->allowRegistration ?? true))) {
            $regCtrl = $controllers['register'] ?? RegisterController::class;
            $pathRegister = trim((string) ($paths['register'] ?? 'register'), '/');

            $regOpts = ['as' => 'register'];
            if (isset($filters['register'])) {
                $regOpts['filter'] = $filters['register'];
            }

            $regAttemptOpts = ['as' => 'register.attempt'];
            if (isset($filters['register'])) {
                $regAttemptOpts['filter'] = $filters['register'];
            }

            $routes->get($pathRegister, [$regCtrl, 'showRegister'], $regOpts);
            $routes->post($pathRegister, [$regCtrl, 'attemptRegister'], $regAttemptOpts);
        }

        // 3. Password Reset
        if (static::isFlowEnabled('password-reset', $only, $except, (bool) ($config->allowPasswordReset ?? true))) {
            $forgotCtrl = $controllers['forgot-password'] ?? $controllers['password-reset'] ?? ForgotPasswordController::class;
            $resetCtrl = $controllers['reset-password'] ?? $controllers['password-reset'] ?? ResetPasswordController::class;

            $pathForgot = trim((string) ($paths['forgot-password'] ?? 'forgot-password'), '/');
            $pathReset = trim((string) ($paths['reset-password'] ?? 'reset-password'), '/');

            $routes->get($pathForgot, [$forgotCtrl, 'showForgot'], ['as' => 'forgot-password']);
            $routes->post($pathForgot, [$forgotCtrl, 'sendResetLink'], ['as' => 'forgot-password.send']);
            $routes->get($pathReset . '/(:segment)', [$resetCtrl, 'showReset'], ['as' => 'reset-password']);
            $routes->get($pathReset, [$resetCtrl, 'showReset'], ['as' => 'reset-password.query']);
            $routes->post($pathReset, [$resetCtrl, 'attemptReset'], ['as' => 'reset-password.attempt']);
        }

        // 4. Magic Link
        if (static::isFlowEnabled('magic-link', $only, $except, (bool) ($config->allowMagicLink ?? true))) {
            $magicCtrl = $controllers['magic-link'] ?? MagicLinkController::class;
            $pathMagic = trim((string) ($paths['magic-link'] ?? 'magic-link'), '/');

            $routes->get($pathMagic, [$magicCtrl, 'showMagicLink'], ['as' => 'magic-link']);
            $routes->post($pathMagic, [$magicCtrl, 'sendLink'], ['as' => 'magic-link.send']);
            $routes->get($pathMagic . '/verify/(:segment)', [$magicCtrl, 'verifyLink'], ['as' => 'magic-link.verify']);
            $routes->get($pathMagic . '/verify', [$magicCtrl, 'verifyLink'], ['as' => 'magic-link.verify.query']);
        }

        // 5. Auth Action / MFA
        if (static::isFlowEnabled('action', $only, $except, true)) {
            $actionCtrl = $controllers['action'] ?? ActionController::class;
            $pathAction = trim((string) ($paths['action'] ?? 'auth/action'), '/');

            $routes->get($pathAction . '/show', [$actionCtrl, 'show'], ['as' => 'auth.action.show']);
            $routes->post($pathAction . '/handle', [$actionCtrl, 'handle'], ['as' => 'auth.action.handle']);
        }

        // 6. Personal Access Tokens
        if (static::isFlowEnabled('tokens', $only, $except, (bool) ($config->allowTokens ?? true))) {
            $tokenCtrl = $controllers['tokens'] ?? TokenController::class;
            $pathTokens = trim((string) ($paths['tokens'] ?? 'api/tokens'), '/');

            $routes->group($pathTokens, static function (RouteCollection $r) use ($tokenCtrl) {
                $r->get('/', [$tokenCtrl, 'index'], ['as' => 'tokens.index']);
                $r->post('/', [$tokenCtrl, 'create'], ['as' => 'tokens.create']);
                $r->delete('(:segment)', [$tokenCtrl, 'revoke'], ['as' => 'tokens.revoke']);
            });
        }
    }

    /**
     * Determine if a flow should be registered based on only, except, and config toggles.
     */
    protected static function isFlowEnabled(string $flow, array $only, array $except, bool $configFlag): bool
    {
        if (! $configFlag) {
            return false;
        }

        if (! empty($only)) {
            return in_array($flow, $only, true);
        }

        return ! in_array($flow, $except, true);
    }
}
