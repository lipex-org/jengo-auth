<?php

declare(strict_types=1);

namespace Jengo\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = Services::auth();
        if (! $auth->check()) {
            return redirect()->to('/login')->with('error', 'Please log in.');
        }

        if (empty($arguments)) {
            return null;
        }

        $user = $auth->user();
        if ($auth->isSuperAdmin($user)) {
            return null;
        }

        foreach ($arguments as $role) {
            if ($auth->hasRole($user, $role)) {
                return null;
            }
        }

        if ($request->hasHeader('Accept') && str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return Services::response()->setStatusCode(403)->setJSON([
                'status'  => 'error',
                'message' => 'Forbidden: Missing required role.',
            ]);
        }

        return Services::response()->setStatusCode(403)->setBody('Forbidden: Missing required role.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
