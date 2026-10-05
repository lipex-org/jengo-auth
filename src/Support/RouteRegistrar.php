<?php

declare(strict_types=1);

namespace Jengo\Auth\Support;

use CodeIgniter\Router\RouteCollection;
use Jengo\Auth\Controllers\ActionController;
use Jengo\Auth\Controllers\ForgotPasswordController;
use Jengo\Auth\Controllers\IdentityController;
use Jengo\Auth\Controllers\LoginController;
use Jengo\Auth\Controllers\MagicLinkController;
use Jengo\Auth\Controllers\OAuthController;
use Jengo\Auth\Controllers\RegisterController;
use Jengo\Auth\Controllers\ResetPasswordController;
use Jengo\Auth\Controllers\SetPasswordController;
use Jengo\Auth\Controllers\SudoController;
use Jengo\Auth\Controllers\TokenController;
use Jengo\Auth\Controllers\TwoFactorSettingsController;

class RouteRegistrar
{
    /**
     * Stores default global configuration options (e.g. prefix, group, filters) configured via RouteRegistrar::configure().
     */
    protected static array $globalOptions = [];

    /**
     * Stores the last active route registration options for introspection.
     */
    protected static array $lastOptions = [];

    /**
     * Stores the list of explicitly published flow names across route registrations.
     */
    protected static array $publishedFlows = [];

    /**
     * Configure default global options (e.g. prefix, group, groupOptions, filters, paths, controllers) for subsequent route registrations.
     */
    public static function configure(array $options): void
    {
        static::$globalOptions = array_replace_recursive(static::$globalOptions, $options);
    } 

    /**
     * Reset or retrieve configured global options.
     */
    public static function getGlobalOptions(): array
    {
        return static::$globalOptions;
    }

    public static function resetGlobalOptions(): void
    {
        static::$globalOptions = [];
        static::$lastOptions = [];
        static::$publishedFlows = [];
    }

    /**
     * Get the options passed to the route registrar.
     */
    public static function getOptions(): array
    {
        return static::$lastOptions;
    }

    /**
     * Check if a specific auth flow is published and enabled in the current route registration.
     */
    public static function isFlowPublished(string $flow): bool
    {
        if (in_array($flow, static::$publishedFlows, true)) {
            return true;
        }

        $options = static::$lastOptions;
        $only = (array) ($options['only'] ?? []);
        $except = (array) ($options['except'] ?? []);
        $config = config('Auth');

        $configFlag = match ($flow) {
            'login'           => (bool) ($config->allowLogin ?? true),
            'register'        => (bool) ($config->allowRegistration ?? true),
            'password-reset'  => (bool) ($config->allowPasswordReset ?? true),
            'magic-link'      => (bool) ($config->allowMagicLink ?? true),
            'tokens'          => (bool) ($config->allowTokens ?? true),
            'social'          => (bool) ($config->allowSocial ?? ($config->social['enabled'] ?? true)),
            'identities'      => (bool) ($config->allowSocial ?? ($config->social['enabled'] ?? true)),
            'action', 'sudo', 'two-factor', 'set-password' => true,
            default           => true,
        };

        return static::isFlowEnabled($flow, $only, $except, $configFlag);
    }

    /**
     * Get an associative array of all known flows and their publication status [flow => bool].
     *
     * @return array<string, bool>
     */
    public static function getPublishedFlows(): array
    {
        $flows = [
            'login',
            'register',
            'password-reset',
            'magic-link',
            'action',
            'sudo',
            'two-factor',
            'tokens',
            'social',
            'set-password',
            'identities',
        ];

        $result = [];
        foreach ($flows as $flow) {
            $result[$flow] = static::isFlowPublished($flow);
        }

        return $result;
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
        $mergedOptions = array_merge(static::$globalOptions, $options);
        if (isset(static::$globalOptions['paths'], $options['paths'])) {
            $mergedOptions['paths'] = array_merge(static::$globalOptions['paths'], $options['paths']);
        }
        if (isset(static::$globalOptions['controllers'], $options['controllers'])) {
            $mergedOptions['controllers'] = array_merge(static::$globalOptions['controllers'], $options['controllers']);
        }
        if (isset(static::$globalOptions['filters'], $options['filters'])) {
            $mergedOptions['filters'] = array_merge(static::$globalOptions['filters'], $options['filters']);
        }
        if (isset(static::$globalOptions['groupOptions'], $options['groupOptions'])) {
            $mergedOptions['groupOptions'] = array_merge(static::$globalOptions['groupOptions'], $options['groupOptions']);
        }

        static::$lastOptions = $mergedOptions;

        $config = config('Auth');
        $prefix = (string) ($mergedOptions['prefix'] ?? $mergedOptions['group'] ?? '');
        $prefix = trim($prefix, '/');

        if ($prefix !== '') {
            $groupOptions = $mergedOptions['groupOptions'] ?? [];
            if (isset($mergedOptions['filter']) && ! isset($groupOptions['filter'])) {
                $groupOptions['filter'] = $mergedOptions['filter'];
            }

            if (! empty($groupOptions)) {
                $routes->group($prefix, $groupOptions, static function (RouteCollection $groupedRoutes) use ($mergedOptions, $config) {
                    static::registerDefinitions($groupedRoutes, $mergedOptions, $config);
                });
            } else {
                $routes->group($prefix, static function (RouteCollection $groupedRoutes) use ($mergedOptions, $config) {
                    static::registerDefinitions($groupedRoutes, $mergedOptions, $config);
                });
            }
        } else {
            static::registerDefinitions($routes, $mergedOptions, $config);
        }
    }

    /**
     * Publish core authentication routes (login, logout, registration, password reset).
     */
    public static function core(RouteCollection $routes, array $options = []): void
    {
        $options['only'] = ['login', 'register', 'password-reset'];
        static::routes($routes, $options);
    }

    /**
     * Publish passwordless Magic Link login and verification routes.
     */
    public static function magicLink(RouteCollection $routes, array $options = []): void
    {
        $options['only'] = ['magic-link'];
        static::routes($routes, $options);
    }

    /**
     * Publish post-auth action pipeline / MFA challenge routes (show, challenge, handle, cancel).
     */
    public static function action(RouteCollection $routes, array $options = []): void
    {
        $options['only'] = ['action'];
        static::routes($routes, $options);
    }

    /**
     * Publish Sudo Mode (step-up authentication) challenge and verification routes.
     */
    public static function sudo(RouteCollection $routes, array $options = []): void
    {
        $options['only'] = ['sudo'];
        static::routes($routes, $options);
    }

    /**
     * Publish Two-Factor Authentication user settings & enrollment routes.
     */
    public static function twoFactor(RouteCollection $routes, array $options = []): void
    {
        $options['only'] = ['two-factor'];
        static::routes($routes, $options);
    }

    /**
     * Publish Personal Access Tokens management routes.
     */
    public static function tokens(RouteCollection $routes, array $options = []): void
    {
        $options['only'] = ['tokens'];
        static::routes($routes, $options);
    }

    /**
     * Publish Social / OAuth authentication routes (redirect & callback).
     */
    public static function social(RouteCollection $routes, array $options = []): void
    {
        $options['only'] = ['social'];
        $options['allowSocial'] = true;
        static::routes($routes, $options);
    }

    /**
     * Publish password provisioning / set password routes.
     */
    public static function setPassword(RouteCollection $routes, array $options = []): void
    {
        $options['only'] = ['set-password'];
        static::routes($routes, $options);
    }

    /**
     * Publish user identity management & unlinking routes.
     */
    public static function identities(RouteCollection $routes, array $options = []): void
    {
        $options['only'] = ['identities'];
        static::routes($routes, $options);
    }

    /**
     * Publish all authentication features and endpoints.
     */
    public static function all(RouteCollection $routes, array $options = []): void
    {
        static::routes($routes, $options);
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
            'action'          => 'action',
            'tokens'          => 'tokens',
            'sudo'            => 'sudo',
            'two-factor'      => 'two-factor',
            'social'          => 'oauth',
            'set-password'    => 'set-password',
            'identities'      => 'identities',
        ];
        $optionPaths = (array) ($options['paths'] ?? []);
        $paths = array_merge($defaultPaths, $optionPaths);

        $defaultControllers = [
            'login'           => LoginController::class,
            'register'        => RegisterController::class,
            'forgot-password' => ForgotPasswordController::class,
            'reset-password'  => ResetPasswordController::class,
            'magic-link'      => MagicLinkController::class,
            'action'          => ActionController::class,
            'tokens'          => TokenController::class,
            'sudo'            => SudoController::class,
            'two-factor'      => TwoFactorSettingsController::class,
            'social'          => OAuthController::class,
            'set-password'    => SetPasswordController::class,
            'identities'      => IdentityController::class,
        ];
        $optionControllers = (array) ($options['controllers'] ?? []);
        $controllers = array_merge($defaultControllers, $optionControllers);

        $filters = (array) ($options['filters'] ?? []);
        $logoutMethod = strtolower((string) ($options['logoutMethod'] ?? ($options['allowGetLogout'] ?? false ? 'get' : null) ?? 'post'));

        // 1. Login & Logout
        if (static::isFlowEnabled('login', $only, $except, (bool) ($config->allowLogin ?? true))) {
            static::$publishedFlows[] = 'login';
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
            static::$publishedFlows[] = 'register';
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
            static::$publishedFlows[] = 'password-reset';
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
            static::$publishedFlows[] = 'magic-link';
            $magicCtrl = $controllers['magic-link'] ?? MagicLinkController::class;
            $pathMagic = trim((string) ($paths['magic-link'] ?? 'magic-link'), '/');

            $routes->get($pathMagic, [$magicCtrl, 'showMagicLink'], ['as' => 'magic-link']);
            $routes->post($pathMagic, [$magicCtrl, 'sendLink'], ['as' => 'magic-link.send']);
            $routes->get($pathMagic . '/verify/(:segment)', [$magicCtrl, 'verifyLink'], ['as' => 'magic-link.verify']);
            $routes->get($pathMagic . '/verify', [$magicCtrl, 'verifyLink'], ['as' => 'magic-link.verify.query']);
        }

        // 5. Auth Action / MFA
        if (static::isFlowEnabled('action', $only, $except, true)) {
            static::$publishedFlows[] = 'action';
            $actionCtrl = $controllers['action'] ?? ActionController::class;
            $pathAction = trim((string) ($paths['action'] ?? 'auth/action'), '/');

            $routes->get($pathAction . '/show', [$actionCtrl, 'show'], ['as' => 'auth.action.show']);
            $routes->post($pathAction . '/challenge', [$actionCtrl, 'challenge'], ['as' => 'auth.action.challenge']);
            $routes->post($pathAction . '/handle', [$actionCtrl, 'handle'], ['as' => 'auth.action.handle']);
            $routes->post($pathAction . '/cancel', [$actionCtrl, 'cancel'], ['as' => 'auth.action.cancel']);
            $routes->get($pathAction . '/cancel', [$actionCtrl, 'cancel'], ['as' => 'auth.action.cancel.get']);
        }

        // 6. Sudo Mode
        if (static::isFlowEnabled('sudo', $only, $except, true)) {
            static::$publishedFlows[] = 'sudo';
            $sudoCtrl = $controllers['sudo'] ?? SudoController::class;
            $pathSudo = trim((string) ($paths['sudo'] ?? 'auth/sudo'), '/');

            $routes->get($pathSudo, [$sudoCtrl, 'index'], ['as' => 'auth.sudo']);
            $routes->post($pathSudo . '/challenge', [$sudoCtrl, 'challenge'], ['as' => 'auth.sudo.challenge']);
            $routes->post($pathSudo . '/verify', [$sudoCtrl, 'verify'], ['as' => 'auth.sudo.verify']);
            $routes->post($pathSudo . '/exit', [$sudoCtrl, 'exit'], ['as' => 'auth.sudo.exit']);
        }

        // 7. Two-Factor Settings & Enrollment
        if (static::isFlowEnabled('two-factor', $only, $except, true)) {
            static::$publishedFlows[] = 'two-factor';
            $twoFactorCtrl = $controllers['two-factor'] ?? TwoFactorSettingsController::class;
            $pathTwoFactor = trim((string) ($paths['two-factor'] ?? 'user/two-factor'), '/');

            $routes->get($pathTwoFactor, [$twoFactorCtrl, 'index'], ['as' => 'two-factor.index']);
            $routes->post($pathTwoFactor . '/enroll/start', [$twoFactorCtrl, 'startEnrollment'], ['as' => 'two-factor.enroll.start']);
            $routes->post($pathTwoFactor . '/enroll/confirm', [$twoFactorCtrl, 'confirmEnrollment'], ['as' => 'two-factor.enroll.confirm']);
            $routes->post($pathTwoFactor . '/unenroll', [$twoFactorCtrl, 'unenroll'], ['as' => 'two-factor.unenroll']);
        }

        // 8. Personal Access Tokens
        if (static::isFlowEnabled('tokens', $only, $except, (bool) ($config->allowTokens ?? true))) {
            static::$publishedFlows[] = 'tokens';
            $tokenCtrl = $controllers['tokens'] ?? TokenController::class;
            $pathTokens = trim((string) ($paths['tokens'] ?? 'tokens'), '/');

            $routes->get($pathTokens, [$tokenCtrl, 'index'], ['as' => 'tokens.index']);
            $routes->post($pathTokens, [$tokenCtrl, 'create'], ['as' => 'tokens.create']);
            $routes->post($pathTokens . '/create', [$tokenCtrl, 'create'], ['as' => 'tokens.create.named']);
            $routes->delete($pathTokens . '/(:segment)', [$tokenCtrl, 'revoke'], ['as' => 'tokens.revoke']);
            $routes->post($pathTokens . '/revoke/(:segment)', [$tokenCtrl, 'revoke'], ['as' => 'tokens.revoke.post']);
        }

        // 9. Social / OAuth Authentication
        $socialEnabled = (bool) ($config->allowSocial ?? ($config->social['enabled'] ?? true));
        if (static::isFlowEnabled('social', $only, $except, $socialEnabled)) {
            static::$publishedFlows[] = 'social';
            $socialCtrl = $controllers['social'] ?? OAuthController::class;
            $pathSocial = trim((string) ($paths['social'] ?? 'oauth'), '/');

            $routes->get($pathSocial . '/(:segment)', [$socialCtrl, 'redirect'], ['as' => 'auth.oauth.redirect']);
            $routes->get($pathSocial . '/callback/(:segment)', [$socialCtrl, 'callback'], ['as' => 'auth.oauth.callback']);
        }

        // 10. Password Provisioning / Set Password
        if (static::isFlowEnabled('set-password', $only, $except, true)) {
            static::$publishedFlows[] = 'set-password';
            $setPasswordCtrl = $controllers['set-password'] ?? SetPasswordController::class;
            $pathSetPassword = trim((string) ($paths['set-password'] ?? 'set-password'), '/');

            $routes->get($pathSetPassword, [$setPasswordCtrl, 'showSetPassword'], ['as' => 'auth.password.set.view']);
            $routes->post($pathSetPassword, [$setPasswordCtrl, 'attemptSetPassword'], ['as' => 'auth.password.set']);
        }

        // 11. User Identity Management & Unlinking
        $identitiesEnabled = (bool) ($config->allowSocial ?? ($config->social['enabled'] ?? true));
        if (static::isFlowEnabled('identities', $only, $except, $identitiesEnabled)) {
            static::$publishedFlows[] = 'identities';
            $identityCtrl = $controllers['identities'] ?? IdentityController::class;
            $pathIdentities = trim((string) ($paths['identities'] ?? 'identities'), '/');

            $routes->get($pathIdentities, [$identityCtrl, 'showIdentities'], ['as' => 'identities.index']);
            $routes->post($pathIdentities . '/unlink/(:segment)', [$identityCtrl, 'unlinkIdentity'], ['as' => 'identities.unlink']);
            $routes->post($pathIdentities . '/unlink', [$identityCtrl, 'unlinkIdentity'], ['as' => 'identities.unlink.post']);
            $routes->delete($pathIdentities . '/(:segment)', [$identityCtrl, 'unlinkIdentity'], ['as' => 'identities.unlink.delete']);
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
