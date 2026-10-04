<?php

declare(strict_types=1);

namespace Jengo\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Auth\Attributes\Authenticate;
use Jengo\Auth\Attributes\Can;
use Jengo\Auth\Attributes\Guest;
use Jengo\Auth\Attributes\Role;
use ReflectionClass;
use ReflectionMethod;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = Services::auth();


        // Explicite guard check via filter arguments takes precedence over controller attributes
        // Standard filter route check: only check guard if explicitly specified in arguments
        $guardName = $arguments[0] ?? null;

        if ($guardName) {
            $guard = $auth->guard($guardName);

            // Since the guard is explicitly specified (and validated), we must check for pending actions here 
            // and redirect before checking for authetication. This is because the user may be logged in but 
            // still have pending actions to complete (e.g. MFA, Terms, etc.)
            if ($auth->hasPendingActions()) {
                return redirect()->to(auth_url('auth.action.show'));
            }

            if (!$guard->check()) {
                return $this->unauthorizedResponse($request);
            }
        }

        // Check if route controller has attributes
        $router = Services::router();
        $controllerName = $router->controllerName();
        $methodName = $router->methodName();

        // skip for closure routes (no controller class)
        if (is_string($controllerName) && class_exists($controllerName)) {
            $attrResult = $this->checkControllerAttributes($controllerName, (string) $methodName, $request);
            if ($attrResult !== null) {
                return $attrResult;
            }
        }

        return;
    }

    public function checkControllerAttributes(string $controllerName, string $methodName, RequestInterface $request)
    {
        $auth = Services::auth();

        if (!class_exists($controllerName)) {
            return null;
        }

        $refClass = new ReflectionClass($controllerName);

        // 1. Check #[Guest] attribute
        $guestAttr = $refClass->getAttributes(Guest::class)[0] ?? null;
        if (method_exists($controllerName, $methodName)) {
            $refMethod = new ReflectionMethod($controllerName, $methodName);
            $guestAttr = $refMethod->getAttributes(Guest::class)[0] ?? $guestAttr;
        }

        if ($guestAttr !== null) {
            if ($auth->check()) {
                /** @var Guest $instance */
                $instance = $guestAttr->newInstance();
                $home = $instance->redirectTo ?? config('Auth')->redirects['login'] ?? '/';
                return redirect()->to($home);
            }
            return null;
        }

        // 2. Check #[Authenticate] attribute
        $authAttr = $refClass->getAttributes(Authenticate::class)[0] ?? null;
        if (method_exists($controllerName, $methodName)) {
            $refMethod = new ReflectionMethod($controllerName, $methodName);
            $authAttr = $refMethod->getAttributes(Authenticate::class)[0] ?? $authAttr;
        }

        if ($authAttr !== null) {
            /** @var Authenticate $instance */
            $instance = $authAttr->newInstance();
            $guard = $instance->guard ? $auth->guard($instance->guard) : $auth->guard();

            // Since the auth attribute is present, then we must check for pending actions here 
            // and redirect before checking for authetication. This is because the user may be 
            // logged in but still have pending actions to complete (e.g. MFA, Terms, etc.)
            if ($auth->hasPendingActions()) {
                return redirect()->to(auth_url('auth.action.show'));
            }

            if (!$guard->check()) {
                return $this->unauthorizedResponse($request);
            }
        }

        // 3. Check #[Role] attribute
        $roleAttr = $refClass->getAttributes(Role::class)[0] ?? null;
        if (method_exists($controllerName, $methodName)) {
            $refMethod = new ReflectionMethod($controllerName, $methodName);
            $roleAttr = $refMethod->getAttributes(Role::class)[0] ?? $roleAttr;
        }

        if ($roleAttr !== null) {
            if (!$auth->check()) {
                return $this->unauthorizedResponse($request);
            }

            /** @var Role $instance */
            $instance = $roleAttr->newInstance();
            $hasAny = false;
            foreach ($instance->roles as $r) {
                if ($auth->hasRole($auth->user(), $r)) {
                    $hasAny = true;
                    break;
                }
            }

            if (!$hasAny && !$auth->isSuperAdmin($auth->user())) {
                return $this->forbiddenResponse($request, "Requires one of roles: " . implode(', ', $instance->roles));
            }
        }

        // 4. Check #[Can] attribute
        if (method_exists($controllerName, $methodName)) {
            $refMethod = new ReflectionMethod($controllerName, $methodName);
            $canAttr = $refMethod->getAttributes(Can::class)[0] ?? null;

            if ($canAttr !== null) {
                if (!$auth->check()) {
                    return $this->unauthorizedResponse($request);
                }

                /** @var Can $instance */
                $instance = $canAttr->newInstance();
                $resource = null;

                if ($instance->resource !== null) {
                    $resource = $request->getGet($instance->resource)
                        ?? $request->getPost($instance->resource)
                        ?? Services::router()->params()[0] ?? null;
                }

                if (!$auth->can($instance->permission, $resource)) {
                    return $this->forbiddenResponse($request, "Unauthorized action [{$instance->permission}].");
                }
            }
        }

        // 5. Check #[Sudo] attribute
        $sudoAttr = $refClass->getAttributes(\Jengo\Auth\Attributes\Sudo::class)[0] ?? null;
        if (method_exists($controllerName, $methodName)) {
            $refMethod = new ReflectionMethod($controllerName, $methodName);
            $sudoAttr = $refMethod->getAttributes(\Jengo\Auth\Attributes\Sudo::class)[0] ?? $sudoAttr;
        }

        if ($sudoAttr !== null) {
            $sudoFilter = new SudoFilter();
            $sudoResult = $sudoFilter->before($request);
            if ($sudoResult !== null) {
                return $sudoResult;
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }

    protected function unauthorizedResponse(RequestInterface $request)
    {
        if ($this->isApiOrJson($request)) {
            return Services::response()
                ->setStatusCode(401)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Unauthenticated.',
                ]);
        }

        return redirect()->to(auth_url('login'))->with('error', 'Please log in to continue.');
    }

    protected function forbiddenResponse(RequestInterface $request, string $message)
    {
        if ($this->isApiOrJson($request)) {
            return Services::response()
                ->setStatusCode(403)
                ->setJSON([
                    'status' => 'error',
                    'message' => $message,
                ]);
        }

        return Services::response()->setStatusCode(403)->setBody($message);
    }

    protected function isApiOrJson(RequestInterface $request): bool
    {
        $accept = $request->getHeaderLine('Accept');
        if (!$accept && isset($_SERVER['HTTP_ACCEPT'])) {
            $accept = (string) $_SERVER['HTTP_ACCEPT'];
        }

        $authHeader = $request->getHeaderLine('Authorization');
        if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = (string) $_SERVER['HTTP_AUTHORIZATION'];
        }

        $uri = $request->getUri()->getPath();
        $cleanPath = ltrim($uri, '/');

        return str_contains($accept, 'application/json')
            || str_starts_with($cleanPath, 'api/')
            || !empty($authHeader);
    }
}
