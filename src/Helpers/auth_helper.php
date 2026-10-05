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

if (!function_exists('sudo')) {
    /**
     * Access the Jengo SudoManager instance.
     */
    function sudo(): \Jengo\Auth\Sudo\SudoManager
    {
        return Services::sudo();
    }
}

if (!function_exists('two_factor')) {
    /**
     * Access the Jengo TwoFactorManager instance.
     */
    function two_factor(): \Jengo\Auth\TwoFactor\TwoFactorManager
    {
        return Services::twoFactor();
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
            'auth.sudo'          => 'auth/sudo',
            'auth.sudo.verify'   => 'auth/sudo/verify',
            'auth.sudo.exit'     => 'auth/sudo/exit',
            'two-factor.index'   => 'user/two-factor',
            'tokens.index'       => 'tokens',
            'tokens.create'      => 'tokens/create',
            'tokens.revoke'      => 'tokens/revoke' . (!empty($params) ? '/' . $params[0] : ''),
        ];

        return site_url($fallbacks[$routeName] ?? $routeName);
    }
}

if (!function_exists('auth_redirect_url')) {
    /**
     * Resolves a redirect destination from config or input.
     * If the target starts with '/' or 'http', it returns it directly; otherwise it resolves it as a named route.
     */
    function auth_redirect_url(string $key, string $default = '/'): string
    {
        $config = function_exists('config') ? config('Auth') : null;
        $target = $config->redirects[$key] ?? $default;

        if (str_starts_with($target, '/') || str_starts_with($target, 'http://') || str_starts_with($target, 'https://')) {
            return $target;
        }

        return auth_url($target);
    }
}

if (!function_exists('auth_request_all')) {
    /**
     * Safely extract all request input parameters (POST, GET, JSON) without throwing on non-JSON/malformed payloads.
     */
    function auth_request_all(?\CodeIgniter\HTTP\RequestInterface $request = null): array
    {
        $req = $request ?? (function_exists('request') ? request() : \Config\Services::request());
        $get = method_exists($req, 'getGet') ? ($req->getGet() ?? []) : [];
        $post = method_exists($req, 'getPost') ? ($req->getPost() ?? []) : [];

        $json = [];
        if (method_exists($req, 'getBody')) {
            $raw = (string) $req->getBody();
            if ($raw !== '') {
                $trimmed = trim($raw);
                if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
                    try {
                        $decoded = json_decode($trimmed, true);
                        if (is_array($decoded)) {
                            $json = $decoded;
                        }
                    } catch (\Throwable) {
                        // Ignore malformed JSON
                    }
                }
            }
        }

        if (!empty($json)) {
            return array_merge($get, $json);
        }

        if (empty($post) && !empty($_POST)) {
            $post = $_POST;
        }

        return array_merge($get, $post);
    }
}

if (!function_exists('auth_request_input')) {
    /**
     * Safely retrieve a specific input key from the request.
     */
    function auth_request_input(?string $key = null, mixed $default = null, ?\CodeIgniter\HTTP\RequestInterface $request = null): mixed
    {
        $all = auth_request_all($request);

        if ($key === null) {
            return $all;
        }

        $req = $request ?? (function_exists('request') ? request() : \Config\Services::request());
        if (method_exists($req, 'getVar')) {
            $val = $req->getVar($key);
            if ($val !== null) {
                return $val;
            }
        }

        return function_exists('data_get') ? data_get($all, $key, $default) : ($all[$key] ?? $default);
    }
}