<?php

declare(strict_types=1);

namespace Jengo\Auth\Actions;

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Auth\Contracts\AuthActionInterface;
use Jengo\Auth\DTOs\AuthResponseData;
use Jengo\Auth\Entities\User;

class EmailActivator implements AuthActionInterface
{
    public function getActionName(): string
    {
        return 'email_activator';
    }

    public function show(RequestInterface $request, User $user): ResponseInterface
    {
        $session = Services::session();

        // Generate 6-digit code or token if not already present in current session
        if (! $session->get('activation_code')) {
            $code = (string) random_int(100000, 999999);
            $session->set('activation_code', $code);
            $session->set('activation_expires', time() + 1800); // 30 mins

            $url = auth_url('auth.action.show');

            // Send activation notification through pluggable notifier
            auth()->getNotifier()->sendActivation($user, $code, $url);

            // Trigger event for custom logging/listeners
            Events::trigger('activationChallenge', $user, $code, $url);
        }

        $data = new AuthResponseData(
            action: 'action.show',
            status: 'info',
            statusCode: 200,
            message: 'An account activation code has been sent to your email.',
            data: ['action' => 'email_activator', 'email' => $user->getEmail()],
            user: $user
        );

        return auth()->renderResponse('action.show', $data, $request);
    }

    public function verify(RequestInterface $request, User $user): bool
    {
        $session = Services::session();
        $storedCode = (string) $session->get('activation_code');
        $expires = (int) $session->get('activation_expires');

        $input = $request->getJSON(true) ?? $request->getPost();
        $submittedCode = (string) ($input['code'] ?? $input['token'] ?? $request->getGet('token') ?? '');

        if (! $storedCode || time() > $expires) {
            $session->remove(['activation_code', 'activation_expires']);
            return false;
        }

        if (hash_equals($storedCode, $submittedCode)) {
            $session->remove(['activation_code', 'activation_expires']);

            // Activate user account in DB
            $user->active = 1;
            $user->status = 'active';
            auth()->getUserModel()->update($user->id, [
                'active' => 1,
                'status' => 'active',
            ]);

            Events::trigger('userActivated', $user);

            return true;
        }

        return false;
    }
}
