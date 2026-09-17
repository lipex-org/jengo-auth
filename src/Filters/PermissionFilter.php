<?php

declare(strict_types=1);

namespace Jengo\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class PermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = Services::auth();
        if (! $auth->check()) {
            if ($this->isApiOrJson($request)) {
                return Services::response()->setStatusCode(401)->setJSON([
                    'status'  => 'error',
                    'message' => 'Unauthenticated.',
                ]);
            }

            return redirect()->to('/login')->with('error', 'Please log in.');
        }

        if (empty($arguments)) {
            return null;
        }

        $user = $auth->user();
        if ($auth->isSuperAdmin($user)) {
            return null;
        }

        foreach ($arguments as $perm) {
            if ($auth->can($perm, null, $user)) {
                return null;
            }
        }

        if ($this->isApiOrJson($request)) {
            return Services::response()->setStatusCode(403)->setJSON([
                'status'  => 'error',
                'message' => 'Forbidden: Missing required permission.',
            ]);
        }

        return Services::response()->setStatusCode(403)->setBody('Forbidden: Missing required permission.');
    }

    protected function isApiOrJson(RequestInterface $request): bool
    {
        $accept = $request->getHeaderLine('Accept');
        if (! $accept && isset($_SERVER['HTTP_ACCEPT'])) {
            $accept = (string) $_SERVER['HTTP_ACCEPT'];
        }

        $authHeader = $request->getHeaderLine('Authorization');
        if (! $authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = (string) $_SERVER['HTTP_AUTHORIZATION'];
        }

        $cleanPath = ltrim($request->getUri()->getPath(), '/');

        return str_contains($accept, 'application/json')
            || str_starts_with($cleanPath, 'api/')
            || ! empty($authHeader);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
