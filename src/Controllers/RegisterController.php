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

        if (auth()->hasPendingActions()) {
            return redirect()->to(auth_url('auth.action.show'));
        }

        if (auth()->check()) {
            return redirect()->to(auth_redirect_url('register', auth_redirect_url('login', '/')));
        }

        $socialProviders = \Jengo\Auth\Support\RouteRegistrar::isFlowPublished('social')
            ? Services::social()->getAvailableProviders()
            : [];

        $data = new AuthResponseData(
            action: 'register.view',
            status: 'success',
            statusCode: 200,
            message: null,
            data: [
                'social_providers' => $socialProviders,
            ]
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

        if (auth()->hasPendingActions()) {
            return redirect()->to(auth_url('auth.action.show'));
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

        // Check if EmailActivator is part of the register pipeline
        $configuredActions = config('Auth')->actions['register'] ?? [];
        $actionList = is_array($configuredActions) ? $configuredActions : [$configuredActions];
        $requiresActivation = in_array(\Jengo\Auth\Actions\EmailActivator::class, $actionList, true);

        // 1. Create User
        $user = new User([
            'username' => $username,
            'active'   => $requiresActivation ? 0 : 1,
            'status'   => $requiresActivation ? 'pending' : 'active',
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
            $session = Services::session();
            $sessionKey = config('Auth')->session['pendingUserKey'] ?? 'auth_pending_user_id';
            $session->set($sessionKey, $user->id);
            $session->set('auth_pending_actions', $pendingActions);
            $session->set('auth_pending_action', $pendingActions[0]);

            $data = new AuthResponseData(
                action: 'register.action_required',
                status: 'info',
                statusCode: 200,
                message: 'Registration successful. Action required.',
                redirectTo: auth_url('auth.action.show'),
                user: $user,
                data: [
                    'use_inertia_location' => true,
                ]
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
            redirectTo: auth_redirect_url('register', '/'),
            data: [
                'use_inertia_location' => true,
            ],
            user: $user
        );

        return $this->renderResponse('register.success', $data);
    }
}
