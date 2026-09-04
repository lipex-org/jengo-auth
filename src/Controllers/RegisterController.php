<?php

declare(strict_types=1);

namespace Jengo\Auth\Controllers;

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Auth\DTOs\AuthResponseData;
use Jengo\Auth\Entities\User;
use Jengo\Auth\Entities\UserIdentity;
use Jengo\Auth\Forms\RegisterFormHandler;
use Jengo\Base\Attributes\Validate;

class RegisterController extends BaseAuthController
{
    /**
     * Display registration form.
     */
    public function showRegister(): ResponseInterface
    {
        if ($disabled = $this->ensureFeatureEnabled('allowRegistration', 'register')) {
            return $disabled;
        }

        if (auth()->check()) {
            return redirect()->to(config('Auth')->redirects['home'] ?? '/dashboard');
        }

        $data = new AuthResponseData(
            action: 'register.view',
            status: 'success',
            statusCode: 200,
            message: null
        );

        return $this->renderResponse('register.view', $data);
    }

    /**
     * Process new user registration using #[Validate] attribute and form() helper.
     */
    #[Validate(RegisterFormHandler::class)]
    public function attemptRegister(): ResponseInterface
    {
        if ($disabled = $this->ensureFeatureEnabled('allowRegistration', 'register')) {
            return $disabled;
        }

        /** @var RegisterFormHandler $form */
        $form = form();
        $email = $form->getEmail();
        $username = $form->getUsername();
        $password = $form->getPassword();

        $auth = auth();

        // Check if email already registered (case-insensitive)
        $existing = $auth->getUserIdentityModel()->where(['type' => 'email_password', 'name' => $email])->first();
        if ($existing) {
            $data = new AuthResponseData(
                action: 'register.validation_failed',
                status: 'error',
                statusCode: 422,
                message: 'Validation failed.',
                errors: ['email' => 'An account with this email already exists.']
            );
            return $this->renderResponse('register.validation_failed', $data);
        }

        // 1. Create User
        $user = new User([
            'username' => $username,
            'active'   => 1,
            'status'   => 'active',
        ]);
        $userId = $auth->getUserModel()->insert($user);
        $user->id = (int) $userId;

        // 2. Create Password Identity
        $hashed = $auth->getHasher()->hash($password);
        $identity = new UserIdentity([
            'user_id' => $user->id,
            'type'    => 'email_password',
            'name'    => $email,
            'secret'  => $hashed,
        ]);
        $auth->getUserIdentityModel()->insert($identity);

        // Trigger CI4 register event
        Events::trigger('register', $user);

        // Check post-register action pipeline
        $configuredActions = config('Auth')->actions['register'] ?? null;
        $actions = is_array($configuredActions) ? array_values(array_filter($configuredActions)) : ($configuredActions ? [$configuredActions] : []);
        $validActions = array_values(array_filter($actions, fn($c) => is_string($c) && class_exists($c)));

        if ($validActions !== []) {
            $session = Services::session();
            $sessionKey = config('Auth')->session['pendingUserKey'] ?? 'auth_pending_user_id';
            $session->set($sessionKey, $user->id);
            $session->set('auth_pending_actions', $validActions);
            $session->set('auth_pending_action', $validActions[0]);

            $data = new AuthResponseData(
                action: 'register.action_required',
                status: 'info',
                statusCode: 200,
                message: 'Registration successful. Action required.',
                redirectTo: auth_url('auth.action.show'),
                user: $user
            );
            return $this->renderResponse('register.action_required', $data);
        }

        // Auto-login
        $auth->login($user);

        $data = new AuthResponseData(
            action: 'register.success',
            status: 'success',
            statusCode: 201,
            message: 'Registration successful.',
            redirectTo: config('Auth')->redirects['home'] ?? '/dashboard',
            user: $user
        );

        return $this->renderResponse('register.success', $data);
    }
}
