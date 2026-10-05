<?php

declare(strict_types=1);

namespace Jengo\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Auth\Attributes\Sudo;
use Jengo\Auth\Sudo\SudoManager;
use ReflectionClass;
use ReflectionMethod;

class SudoFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = Services::auth();

        // 1. Ensure user is authenticated first
        if (!$auth->check()) {
            return $this->unauthenticatedResponse($request);
        }

        $user = $auth->user();
        $sudo = Services::sudo();

        $lifetime = $arguments[0] ?? '2 hours';
        $forceFresh = false;
        $allowedFactors = [];
        $redirectTo = auth_url('auth.sudo') ?? '/auth/sudo';

        // 2. Inspect controller method for #[Sudo] attribute
        $router = Services::router();
        $controllerName = $router->controllerName();
        $methodName = (string) $router->methodName();

        if (is_string($controllerName) && class_exists($controllerName)) {
            $refClass = new ReflectionClass($controllerName);
            $sudoAttr = $refClass->getAttributes(Sudo::class)[0] ?? null;

            if (method_exists($controllerName, $methodName)) {
                $refMethod = new ReflectionMethod($controllerName, $methodName);
                $sudoAttr = $refMethod->getAttributes(Sudo::class)[0] ?? $sudoAttr;
            }

            if ($sudoAttr !== null) {
                /** @var Sudo $instance */
                $instance = $sudoAttr->newInstance();
                $lifetime = $instance->lifetime;
                $forceFresh = $instance->forceFresh;
                $allowedFactors = $instance->factors;
                if ($instance->redirectTo !== null) {
                    $redirectTo = $instance->redirectTo;
                }
            }
        }

        // 3. Check if active Sudo session exists
        if ($sudo->check($lifetime, $forceFresh)) {
            return null; // Sudo active! Proceed
        }

        // 4. Save intended destination URL in session
        $intendedUrl = (string) $request->getUri();
        Services::session()->set(SudoManager::SESSION_INTENDED, $intendedUrl);

        // 5. Produce response based on client type
        return $this->sudoRequiredResponse($request, $user, $allowedFactors, $redirectTo);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }

    protected function sudoRequiredResponse(
        RequestInterface $request,
        $user,
        array $allowedFactors,
        string $redirectTo
    ) {
        $twoFactor = Services::twoFactor();
        $availableFactors = $twoFactor->getEnrolledFactorsSummary($user, $allowedFactors);

        if ($this->isApiOrJson($request)) {
            return Services::response()
                ->setStatusCode(403)
                ->setHeader('X-Jengo-Sudo-Required', 'true')
                ->setJSON([
                    'status'            => 'error',
                    'sudo_required'     => true,
                    'message'           => 'This action requires elevated verification (Sudo Mode).',
                    'available_factors' => $availableFactors,
                ]);
        }

        return redirect()->to($redirectTo)->with('warning', 'Please verify your identity to proceed.');
    }

    protected function unauthenticatedResponse(RequestInterface $request)
    {
        if ($this->isApiOrJson($request)) {
            return Services::response()
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Unauthenticated.',
                ]);
        }

        return redirect()->to('/login')->with('error', 'Please log in to continue.');
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

        $uri = $request->getUri()->getPath();
        $cleanPath = ltrim($uri, '/');

        return str_contains($accept, 'application/json')
            || str_starts_with($cleanPath, 'api/')
            || ! empty($authHeader);
    }
}
