<?php

declare(strict_types=1);

namespace Jengo\Auth\Controllers;

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Auth\Contracts\AuthActionInterface;
use Jengo\Auth\DTOs\AuthResponseData;

class ActionController extends BaseAuthController
{
    /**
     * Display the challenge for the active post-auth action (e.g. 2FA/MFA).
     */
    public function show(): ResponseInterface
    {
        $session = Services::session();
        $sessionKey = config('Auth')->session['pendingUserKey'] ?? 'auth_pending_user_id';
        $userId = $session->get($sessionKey);
        $actionClass = $session->get('auth_pending_action');

        if (! $userId || ! $actionClass || ! class_exists($actionClass)) {
            return $this->notFoundResponse('action.invalid');
        }

        $user = auth()->getUserModel()->find((int) $userId);
        if (! $user) {
            return $this->notFoundResponse('action.invalid');
        }

        /** @var AuthActionInterface $actionInstance */
        $actionInstance = new $actionClass();

        return $actionInstance->show($this->request, $user);
    }

    /**
     * Process verification for the active post-auth action.
     * Always returns 404 on any verification failure to prevent enumeration or revealing internals.
     */
    public function handle(): ResponseInterface
    {
        $session = Services::session();
        $sessionKey = config('Auth')->session['pendingUserKey'] ?? 'auth_pending_user_id';
        $userId = $session->get($sessionKey);
        $actionClass = $session->get('auth_pending_action');

        if (! $userId || ! $actionClass || ! class_exists($actionClass)) {
            return $this->notFoundResponse('action.invalid');
        }

        $user = auth()->getUserModel()->find((int) $userId);
        if (! $user) {
            return $this->notFoundResponse('action.invalid');
        }

        /** @var AuthActionInterface $actionInstance */
        $actionInstance = new $actionClass();

        $verified = $actionInstance->verify($this->request, $user);
        if (! $verified) {
            // Strict 404 on failure as required
            return $this->notFoundResponse('action.failed');
        }

        // Action completed successfully: clear pending session and finalize login
        $session->remove([$sessionKey, 'auth_pending_action']);

        auth()->login($user);
        Events::trigger('login', $user);
        Events::trigger('actionCompleted', $user, $actionInstance->getActionName());

        $data = new AuthResponseData(
            action: 'action.success',
            status: 'success',
            statusCode: 200,
            message: 'Authentication completed successfully.',
            redirectTo: config('Auth')->redirects['home'] ?? '/dashboard',
            user: $user
        );

        return $this->renderResponse('action.success', $data);
    }
}
