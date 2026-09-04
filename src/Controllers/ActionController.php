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
     * Helper to retrieve the current pending actions list and user.
     *
     * @return array{0: array<string>, 1: \Jengo\Auth\Entities\User}|null
     */
    protected function getPendingContext(): ?array
    {
        $session = Services::session();
        $sessionKey = config('Auth')->session['pendingUserKey'] ?? 'auth_pending_user_id';
        $userId = $session->get($sessionKey);

        $actions = $session->get('auth_pending_actions');
        if (! is_array($actions) || empty($actions)) {
            $single = $session->get('auth_pending_action');
            $actions = $single ? [$single] : [];
        }

        if (! $userId || empty($actions)) {
            return null;
        }

        $user = auth()->getUserModel()->find((int) $userId);
        if (! $user) {
            return null;
        }

        return [$actions, $user];
    }

    /**
     * Display the challenge for the active post-auth action in the pipeline.
     */
    public function show(): ResponseInterface
    {
        $context = $this->getPendingContext();
        if (! $context) {
            return $this->notFoundResponse('action.invalid');
        }

        [$actions, $user] = $context;
        $currentActionClass = $actions[0];

        if (! class_exists($currentActionClass)) {
            return $this->notFoundResponse('action.invalid');
        }

        /** @var AuthActionInterface $actionInstance */
        $actionInstance = new $currentActionClass();

        return $actionInstance->show($this->request, $user);
    }

    /**
     * Process verification for the active post-auth action.
     * If more actions remain in the pipeline, shifts to the next action.
     * Once all actions succeed, finalizes user login.
     * Always returns 404 on any verification failure to prevent enumeration or revealing internals.
     */
    public function handle(): ResponseInterface
    {
        $context = $this->getPendingContext();
        if (! $context) {
            return $this->notFoundResponse('action.invalid');
        }

        [$actions, $user] = $context;
        $session = Services::session();
        $sessionKey = config('Auth')->session['pendingUserKey'] ?? 'auth_pending_user_id';
        $currentActionClass = $actions[0];

        if (! class_exists($currentActionClass)) {
            return $this->notFoundResponse('action.invalid');
        }

        /** @var AuthActionInterface $actionInstance */
        $actionInstance = new $currentActionClass();

        $verified = $actionInstance->verify($this->request, $user);
        if (! $verified) {
            // Strict 404 on failure as required
            return $this->notFoundResponse('action.failed');
        }

        // Trigger actionCompleted event for this individual action
        Events::trigger('actionCompleted', $user, $actionInstance->getActionName());

        // Shift completed action off pipeline queue
        array_shift($actions);

        if (! empty($actions)) {
            // More actions remain in pipeline: update queue and redirect to next action
            $session->set('auth_pending_actions', array_values($actions));
            $session->set('auth_pending_action', $actions[0]);

            $data = new AuthResponseData(
                action: 'action.next',
                status: 'info',
                statusCode: 200,
                message: 'Next authentication action required.',
                redirectTo: auth_url('auth.action.show'),
                user: $user
            );

            return $this->renderResponse('action.next', $data);
        }

        // All pipeline actions completed successfully: clear session and finalize login
        $session->remove([$sessionKey, 'auth_pending_actions', 'auth_pending_action']);

        auth()->login($user);
        Events::trigger('login', $user);

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
