<?php

declare(strict_types=1);

namespace Jengo\Auth\Config;

use Jengo\Auth\Filters\AuthFilter;
use Jengo\Auth\Filters\PermissionFilter;
use Jengo\Auth\Filters\RoleFilter;

class Registrar
{
    /**
     * Registers filters with CodeIgniter 4.
     */
    public static function Filters(): array
    {
        return [
            'aliases' => [
                'jengo.auth'       => AuthFilter::class,
                'jengo.role'       => RoleFilter::class,
                'jengo.permission' => PermissionFilter::class,
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
