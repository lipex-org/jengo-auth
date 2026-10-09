<?php

declare(strict_types=1);

namespace Jengo\Auth\Controllers;

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Auth\Contracts\AuthActionInterface;
use Jengo\Auth\DTOs\AuthResponseData;
use Jengo\Base\Container\Container;

class ActionController extends BaseAuthController
{
    /**
     * Display the challenge for the active post-auth action in the pipeline.
     */
    public function show(): ResponseInterface
    {
        $context = $this->getPendingContext();
        if (! $context) {
            $fallbackUrl = auth()->check()
                ? auth_redirect_url('login', '/')
                : auth_redirect_url('logout', 'login');

            return redirect()->to($fallbackUrl);
        }

        [$actions, $user] = $context;

        // Loop until an action is pending or all actions are completed
        while (! empty($actions)) {
            $currentActionClass = $actions[0];

            if (! class_exists($currentActionClass)) {
                throw new \RuntimeException("Authentication action class [{$currentActionClass}] does not exist.");
            }

            /** @var AuthActionInterface $actionInstance */
            $actionInstance = $this->resolveActionInstance($currentActionClass);

            // Check if action is pending
            $isPending = method_exists($actionInstance, 'isPending')
                ? $this->callActionMethod($actionInstance, 'isPending', ['request' => $this->request, 'user' => $user])
                : true;

            if ($isPending) {
                // Update session state to current pending action
                $session = Services::session();
                $session->set('auth_pending_actions', array_values($actions));
                $session->set('auth_pending_action', $currentActionClass);

                return $this->callActionMethod($actionInstance, 'show', ['request' => $this->request, 'user' => $user]);
            }

            // Action is not pending; trigger actionSkipped event and shift
            Events::trigger('actionSkipped', $user, $actionInstance->getActionName());
            array_shift($actions);
        }

        // All pipeline actions completed/skipped: finalize login
        return $this->finalizeLogin($user);
    }

    /**
     * Re-issue or trigger a challenge on the active post-auth action (e.g. resend 2FA code).
     */
    public function challenge(): ResponseInterface
    {
        $context = $this->getPendingContext();
        if (! $context) {
            $fallbackUrl = auth()->check()
                ? auth_redirect_url('login', '/')
                : auth_redirect_url('logout', 'login');

            return redirect()->to($fallbackUrl);
        }

        [$actions, $user] = $context;
        $currentActionClass = $actions[0];

        if (! class_exists($currentActionClass)) {
            throw new \RuntimeException("Authentication action class [{$currentActionClass}] does not exist.");
        }

        /** @var AuthActionInterface $actionInstance */
        $actionInstance = $this->resolveActionInstance($currentActionClass);

        if (method_exists($actionInstance, 'challenge')) {
            return $this->callActionMethod($actionInstance, 'challenge', ['request' => $this->request, 'user' => $user]);
        }

        // Fallback to show() if action does not define specific challenge logic
        return $this->callActionMethod($actionInstance, 'show', ['request' => $this->request, 'user' => $user]);
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
            $fallbackUrl = auth()->check()
                ? auth_redirect_url('login', '/')
                : auth_redirect_url('logout', 'login');

            return redirect()->to($fallbackUrl);
        }

        [$actions, $user] = $context;
        $session = Services::session();
        $currentActionClass = $actions[0];

        if (! class_exists($currentActionClass)) {
            throw new \RuntimeException("Authentication action class [{$currentActionClass}] does not exist.");
        }

        /** @var AuthActionInterface $actionInstance */
        $actionInstance = $this->resolveActionInstance($currentActionClass);

        $verified = (bool) $this->callActionMethod($actionInstance, 'verify', ['request' => $this->request, 'user' => $user]);
        if (! $verified) {
            // Strict 404 on failure as required
            return $this->notFoundResponse('action.failed');
        }

        // Trigger actionCompleted event for this individual action
        Events::trigger('actionCompleted', $user, $actionInstance->getActionName());

        // Shift completed action off pipeline queue
        array_shift($actions);

        // Advance to next pending action in pipeline or finalize login
        return $this->advancePipeline($actions, $user);
    }

    /**
     * Advance the pipeline to the next pending action, automatically skipping non-pending actions.
     *
     * @param array<string> $actions
     */
    protected function advancePipeline(array $actions, \Jengo\Auth\Entities\User $user): ResponseInterface
    {
        $session = Services::session();

        while (! empty($actions)) {
            $nextActionClass = $actions[0];

            if (! class_exists($nextActionClass)) {
                throw new \RuntimeException("Authentication action class [{$nextActionClass}] does not exist.");
            }

            /** @var AuthActionInterface $actionInstance */
            $actionInstance = $this->resolveActionInstance($nextActionClass);

            $isPending = method_exists($actionInstance, 'isPending')
                ? $this->callActionMethod($actionInstance, 'isPending', ['request' => $this->request, 'user' => $user])
                : true;

            if ($isPending) {
                $session->set('auth_pending_actions', array_values($actions));
                $session->set('auth_pending_action', $nextActionClass);

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

            Events::trigger('actionSkipped', $user, $actionInstance->getActionName());
            array_shift($actions);
        }

        return $this->finalizeLogin($user);
    }

    /**
     * Finalize login once all pipeline actions have completed or been skipped.
     */
    protected function finalizeLogin(\Jengo\Auth\Entities\User $user): ResponseInterface
    {
        $session = Services::session();
        $sessionKey = config('Auth')->session['pendingUserKey'] ?? 'auth_pending_user_id';

        $session->remove([$sessionKey, 'auth_pending_actions', 'auth_pending_action']);

        auth()->login($user);
        Events::trigger('login', $user);

        $data = new AuthResponseData(
            action: 'action.success',
            status: 'success',
            statusCode: 200,
            message: 'Authentication completed successfully.',
            redirectTo: auth_redirect_url('action', auth_redirect_url('login', '/')),
            data: [
                'use_inertia_location' => true,
            ],
            user: $user
        );

        return $this->renderResponse('action.success', $data);
    }

    /**
     * Cancel the ongoing authentication action pipeline and clear pending session state.
     */
    public function cancel(): ResponseInterface
    {
        auth()->cancelPendingActions();

        $data = new AuthResponseData(
            action: 'action.cancelled',
            status: 'info',
            statusCode: 200,
            message: 'Authentication action cancelled.',
            redirectTo: auth_redirect_url('logout', 'login'),
            data: [
                'use_inertia_location' => true,
            ]
        );

        return $this->renderResponse('action.cancelled', $data);
    }

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
     * Resolve an AuthActionInterface instance using Jengo Container if available.
     */
    protected function resolveActionInstance(string $actionClass): AuthActionInterface
    {
        return Container::getInstance()->make($actionClass);
    }

    /**
     * Call an AuthActionInterface method using Jengo Container for auto-wiring dependencies.
     */
    protected function callActionMethod(AuthActionInterface $action, string $method, array $parameters = []): mixed
    {
        return Container::getInstance()->make([$action, $method], $parameters);
    }
}
