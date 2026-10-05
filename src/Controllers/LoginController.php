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

        if (auth()->hasPendingActions()) {
            return redirect()->to(auth_url('auth.action.show'));
        }

        if (auth()->check()) {
            return redirect()->to(auth_redirect_url('login', '/'));
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

        if (auth()->hasPendingActions()) {
            return redirect()->to(auth_url('auth.action.show'));
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
            'email' => $identifier,
            'username' => $identifier,
            'password' => $password,
        ], $remember);

        if (!$result->isSuccess()) {
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

        // Filter for only actions that are actually pending for this user
        $pendingActions = [];
        foreach ($validActions as $actionClass) {
            /** @var \Jengo\Auth\Contracts\AuthActionInterface $actionInstance */
            $actionInstance = new $actionClass();
            $isPending = method_exists($actionInstance, 'isPending')
                ? $actionInstance->isPending($this->request, $user)
                : true;

            if ($isPending) {
                $pendingActions[] = $actionClass;
            } else {
                Events::trigger('actionSkipped', $user, $actionInstance->getActionName());
            }
        }

        if ($pendingActions !== []) {
            // Un-authenticate from main session guard while preserving pending action keys
            $auth->guard()->logout();

            $session = Services::session();
            $sessionKey = config('Auth')->session['pendingUserKey'] ?? 'auth_pending_user_id';
            $session->set($sessionKey, $user->id);
            $session->set('auth_pending_actions', $pendingActions);
            $session->set('auth_pending_action', $pendingActions[0]);

            $data = new AuthResponseData(
                action: 'login.action_required',
                status: 'info',
                statusCode: 200,
                message: 'Additional authentication action required.',
                redirectTo: auth_url('auth.action.show'),
                user: $user,
                data: [
                    'use_inertia_location' => true,
                ]
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
            redirectTo: auth_redirect_url('login', '/'),
            data: [
                'use_inertia_location' => true,
            ],
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
            redirectTo: auth_redirect_url('logout', 'login'),
            data: [
                'use_inertia_location' => true
            ]
        );

        return $this->renderResponse('logout.success', $data);
    }
}
