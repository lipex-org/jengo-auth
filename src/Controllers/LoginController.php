<?php

declare(strict_types=1);

namespace Jengo\Auth\Controllers;

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Auth\DTOs\AuthResponseData;
use Jengo\Auth\Forms\LoginFormHandler;
use Jengo\Base\Attributes\Validate;

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
     * Process login credentials using #[Validate] attribute and form() helper.
     */
    #[Validate(LoginFormHandler::class)]
    public function attemptLogin(): ResponseInterface
    {
        if ($disabled = $this->ensureFeatureEnabled('allowLogin', 'login')) {
            return $disabled;
        }

        /** @var LoginFormHandler $form */
        $form = form();
        $identifier = $form->getIdentifier();
        $password = $form->getPassword();

        $remember = false;
        if ($this->isFeatureEnabled('allowRemembering')) {
            $remember = $form->isRemember();
        }

        $auth = auth();

        // Dual-bucket rate limiting (Account throttle + IP/client composite throttle)
        $rateLimiter = $auth->getRateLimiter();
        $maxAttempts = config('Auth')->throttling['maxAttempts'] ?? 5;
        $decaySeconds = (int) ((config('Auth')->throttling['decayMinutes'] ?? 1) * 60);

        if ($rateLimiter->isThrottled($this->request, (string) $identifier, 'login', $maxAttempts, $decaySeconds)) {
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
            $rateLimiter->recordFailure($this->request, (string) $identifier, 'login', $decaySeconds);
            Events::trigger('failedLogin', ['identifier' => $identifier, 'ip' => $this->request->getIPAddress()]);

            $data = new AuthResponseData(
                action: 'login.failed',
                status: 'error',
                statusCode: 401,
                message: $result->error ?? 'Invalid credentials.',
                errors: ['credentials' => $result->error ?? 'Invalid credentials.']
            );
            return $this->renderResponse('login.failed', $data);
        }

        $rateLimiter->recordSuccess($this->request, (string) $identifier, 'login');
        $user = $result->getUser();

        // Check if post-login auth action pipeline is configured (e.g. MFA, Terms)
        $configuredActions = config('Auth')->actions['login'] ?? null;
        $actions = is_array($configuredActions) ? array_values(array_filter($configuredActions)) : ($configuredActions ? [$configuredActions] : []);
        $validActions = array_values(array_filter($actions, fn($c) => is_string($c) && class_exists($c)));

        if ($validActions !== []) {
            $session = Services::session();
            $sessionKey = config('Auth')->session['pendingUserKey'] ?? 'auth_pending_user_id';
            $session->set($sessionKey, $user->id);
            $session->set('auth_pending_actions', $validActions);
            $session->set('auth_pending_action', $validActions[0]);

            // Log user out of full auth until action completes
            $auth->logout();

            $data = new AuthResponseData(
                action: 'login.action_required',
                status: 'info',
                statusCode: 200,
                message: 'Additional authentication action required.',
                redirectTo: auth_url('auth.action.show'),
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
            redirectTo: config('Auth')->redirects['logout'] ?? auth_url('login')
        );

        return $this->renderResponse('logout.success', $data);
    }
}
