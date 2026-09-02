<?php

declare(strict_types=1);

namespace Jengo\Auth\Controllers;

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Auth\DTOs\AuthResponseData;

class LoginController extends BaseAuthController
{
    /**
     * Display the login form / component.
     */
    public function showLogin(): ResponseInterface
    {
        if ($disabled = $this->ensureFeatureEnabled('allowLogin', 'login')) {
            return $disabled;
        }

        if (auth()->check()) {
            return redirect()->to(config('Auth')->redirects['home'] ?? '/dashboard');
        }

        $data = new AuthResponseData(
            action: 'login.view',
            status: 'success',
            statusCode: 200,
            message: null
        );

        return $this->renderResponse('login.view', $data);
    }

    /**
     * Process login credentials (supports JSON and POST form bodies).
     */
    public function attemptLogin(): ResponseInterface
    {
        if ($disabled = $this->ensureFeatureEnabled('allowLogin', 'login')) {
            return $disabled;
        }

        $payload = $this->extractPayload();
        $auth = auth();

        $identifier = $payload['email'] ?? $payload['username'] ?? null;
        $password = $payload['password'] ?? null;
        
        $remember = false;
        if ($this->isFeatureEnabled('allowRemembering')) {
            $remember = (bool) ($payload['remember'] ?? false);
        }

        if (! $identifier || ! $password) {
            $data = new AuthResponseData(
                action: 'login.validation_failed',
                status: 'error',
                statusCode: 422,
                message: 'Email/Username and Password are required.',
                errors: ['identifier' => 'Identifier is required', 'password' => 'Password is required']
            );
            return $this->renderResponse('login.validation_failed', $data);
        }

        // Rate limiting with composite guest fingerprint (identifier + IP + client entropy)
        $throttleKey = $auth->getRateLimiter()->forGuest($this->request, (string) $identifier, 'login');
        $maxAttempts = config('Auth')->throttling['maxAttempts'] ?? 5;
        if ($auth->getRateLimiter()->tooManyAttempts($throttleKey, $maxAttempts)) {
            $data = new AuthResponseData(
                action: 'login.throttled',
                status: 'error',
                statusCode: 429,
                message: 'Too many login attempts. Please try again later.'
            );
            return $this->renderResponse('login.throttled', $data);
        }

        $result = $auth->attempt([
            'email'    => $identifier,
            'username' => $identifier,
            'password' => $password,
        ], $remember);

        if (! $result->isSuccess()) {
            $auth->getRateLimiter()->hit($throttleKey, 60);
            Events::trigger('failedLogin', ['identifier' => $identifier, 'ip' => $this->request->getIPAddress()]);

            $data = new AuthResponseData(
                action: 'login.failed',
                status: 'error',
                statusCode: 401,
                message: $result->getMessage() ?? 'Invalid credentials.',
                errors: ['credentials' => $result->getMessage() ?? 'Invalid credentials.']
            );
            return $this->renderResponse('login.failed', $data);
        }

        $auth->getRateLimiter()->clear($throttleKey);
        $user = $result->getUser();

        // Check if post-login auth action is configured (e.g. MFA)
        $actionClass = config('Auth')->actions['login'] ?? null;
        if ($actionClass && class_exists($actionClass)) {
            $session = Services::session();
            $sessionKey = config('Auth')->session['pendingUserKey'] ?? 'auth_pending_user_id';
            $session->set($sessionKey, $user->id);
            $session->set('auth_pending_action', $actionClass);

            // Log user out of full auth until action completes
            $auth->logout();

            $data = new AuthResponseData(
                action: 'login.action_required',
                status: 'info',
                statusCode: 200,
                message: 'Additional authentication action required.',
                redirectTo: '/auth/action/show',
                user: $user
            );
            return $this->renderResponse('login.action_required', $data);
        }

        // Trigger standard CI4 login event
        Events::trigger('login', $user);

        $data = new AuthResponseData(
            action: 'login.success',
            status: 'success',
            statusCode: 200,
            message: 'Successfully authenticated.',
            redirectTo: config('Auth')->redirects['home'] ?? '/dashboard',
            user: $user
        );

        return $this->renderResponse('login.success', $data);
    }

    /**
     * Log out current authenticated session.
     */
    public function logout(): ResponseInterface
    {
        $user = auth()->user();
        auth()->logout();

        if ($user) {
            Events::trigger('logout', $user);
        }

        $data = new AuthResponseData(
            action: 'logout.success',
            status: 'success',
            statusCode: 200,
            message: 'Logged out successfully.',
            redirectTo: config('Auth')->redirects['logout'] ?? '/login'
        );

        return $this->renderResponse('logout.success', $data);
    }
}
