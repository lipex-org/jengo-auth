<?php

declare(strict_types=1);

namespace Jengo\Auth\Middleware;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class InertiaAuthSharedProps implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = Services::auth();

        if (class_exists('Jengo\\Inertia\\Inertia')) {
            $user = $auth->user();

            $authData = [
                'user'        => null,
                'roles'       => [],
                'permissions' => [],
            ];

            if ($user !== null) {
                $roles = $auth->user($user)->get()->roles();
                $permissions = $auth->user($user)->get()->permissions()->all();

                $authData = [
                    'user' => [
                        'id'       => function_exists('sqids_hash') ? sqids_hash((int) $user->id) : $user->id,
                        'username' => $user->username,
                        'email'    => $user->getEmail(),
                        'active'   => (bool) $user->active,
                    ],
                    'roles'       => array_map(fn($r) => is_object($r) ? ($r->name ?? $r->roleId ?? $r) : $r, $roles),
                    'permissions' => array_map(fn($p) => is_object($p) ? ($p->name ?? $p->permissionId ?? $p) : $p, $permissions),
                ];
            }

            \Jengo\Inertia\Inertia::share('auth', $authData);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
