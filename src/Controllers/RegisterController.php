<?php

declare(strict_types=1);

namespace Jengo\Auth\Controllers;

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Auth\DTOs\AuthResponseData;
use Jengo\Auth\Entities\User;
use Jengo\Auth\Entities\UserIdentity;

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
     * Process new user registration.
     */
    public function attemptRegister(): ResponseInterface
    {
        if ($disabled = $this->ensureFeatureEnabled('allowRegistration', 'register')) {
            return $disabled;
        }

        $payload = $this->extractPayload();
        $auth = auth();

        $email = $payload['email'] ?? null;
        $username = $payload['username'] ?? null;
        $password = $payload['password'] ?? null;
        $passwordConfirm = $payload['password_confirm'] ?? null;

        $errors = [];
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email address is required.';
        }
        if (! $password || strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long.';
        }
        if ($passwordConfirm !== null && $password !== $passwordConfirm) {
            $errors['password_confirm'] = 'Passwords do not match.';
        }

        // Check if email already registered
        if (! empty($email)) {
            $existing = $auth->getUserIdentityModel()->where(['type' => 'email_password', 'name' => $email])->first();
            if ($existing) {
                $errors['email'] = 'An account with this email already exists.';
            }
        }

        if (! empty($errors)) {
            $data = new AuthResponseData(
                action: 'register.validation_failed',
                status: 'error',
                statusCode: 422,
                message: 'Validation failed.',
                errors: $errors
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
        $actionClass = config('Auth')->actions['register'] ?? null;
        if ($actionClass && class_exists($actionClass)) {
            $session = Services::session();
            $sessionKey = config('Auth')->session['pendingUserKey'] ?? 'auth_pending_user_id';
            $session->set($sessionKey, $user->id);
            $session->set('auth_pending_action', $actionClass);

            $data = new AuthResponseData(
                action: 'register.action_required',
                status: 'info',
                statusCode: 200,
                message: 'Registration successful. Action required.',
                redirectTo: '/auth/action/show',
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
