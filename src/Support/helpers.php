<?php

declare(strict_types=1);

use Jengo\Auth\Config\Services;
use Jengo\Auth\Support\AuthManager;
use Vima\Core\VimaManager;

if (!function_exists('auth')) {
    /**
     * Access the Jengo AuthManager instance.
     */
    function auth(): AuthManager
    {
        return Services::auth();
    }
}

if (!function_exists('vima')) {
    /**
     * Access the Vima Access Manager instance.
     */
    function vima(): VimaManager
    {
        return Services::vima();
    }
}

if (!function_exists('can')) {
    /**
     * Check if the authenticated user has the given permission.
     */
    function can(string $permission, mixed ...$arguments): bool
    {
        return auth()->can($permission, ...$arguments);
    }
}

if (!function_exists('can_any')) {
    /**
     * Performs authorization checks on each permission given and returns true on the first permitted action.
     */
    function can_any(array $permissions, mixed ...$arguments): bool
    {
        foreach ($permissions as $perm) {
            if (can($perm, ...$arguments)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('can_all')) {
    /**
     * Performs authorization checks on each permission given and returns false on the first non-permitted action.
     */
    function can_all(array $permissions, mixed ...$arguments): bool
    {
        foreach ($permissions as $perm) {
            if (!can($perm, ...$arguments)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('auth_url')) {
    /**
     * Resolves a named auth route URL using url_to(), falling back gracefully to site_url if routes have not been registered.
     */
    function auth_url(string $routeName, mixed ...$params): string
    {
        try {
            $url = url_to($routeName, ...$params);
            if ($url) {
                return $url;
            }
        } catch (\Throwable) {
            // Fallback for standalone invocations
        }

        $fallbacks = [
            'login'              => 'login',
            'logout'             => 'logout',
            'register'           => 'register',
            'forgot-password'    => 'forgot-password',
            'reset-password'     => 'reset-password' . (!empty($params) ? '/' . $params[0] : ''),
            'magic-link'         => 'magic-link',
            'magic-link.verify'  => 'magic-link/verify' . (!empty($params) ? '/' . $params[0] : ''),
            'auth.action.show'   => 'auth/action/show',
            'auth.action.handle' => 'auth/action/handle',
            'tokens.index'       => 'api/tokens',
        ];

        return site_url($fallbacks[$routeName] ?? $routeName);
    }
}
