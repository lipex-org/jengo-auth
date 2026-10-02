<?php

declare(strict_types=1);

namespace Jengo\Auth\Config;

use Jengo\Auth\Filters\AuthFilter;
use Jengo\Auth\Filters\PermissionFilter;
use Jengo\Auth\Filters\RoleFilter;

class Registrar
{
    /**
     * Registers filter aliases with CodeIgniter 4.
     */
    public static function Filters(): array
    {
        return [
            'aliases' => [
                'auth'       => AuthFilter::class,
                'session'    => AuthFilter::class,
                'sudo'       => \Jengo\Auth\Filters\SudoFilter::class,
                'tokens'     => \Jengo\Auth\Filters\TokenFilter::class,
                'role'       => RoleFilter::class,
                'permission' => PermissionFilter::class,
            ],
        ];
    }

    /**
     * Registers default Vima authorization config overrides.
     */
    public static function Vima(): array
    {
        return [
            'superAdmin' => [
                'role'   => 'superadmin',
                'bypass' => true,
            ],
            'user' => [
                'current'  => static function () {
                    try {
                        return function_exists('auth') ? auth()->user() : (function_exists('service') ? service('auth')->user() : null);
                    } catch (\Throwable) {
                        return null;
                    }
                },
                'resolver' => static function ($user) {
                    if (is_object($user) && method_exists($user, 'vimaGetId')) {
                        return $user->vimaGetId();
                    }
                    if (is_object($user)) {
                        return $user->id ?? null;
                    }
                    if (is_array($user)) {
                        return $user['id'] ?? null;
                    }
                    return is_scalar($user) ? (string) $user : null;
                },
            ],
        ];
    }
}
