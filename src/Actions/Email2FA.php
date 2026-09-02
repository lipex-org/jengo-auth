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

class Email2FA implements AuthActionInterface
{
    public function getActionName(): string
    {
        return 'email_2fa';
    }

    public function show(RequestInterface $request, User $user): ResponseInterface
    {
        $session = Services::session();

        // Generate 6-digit code if not already present
        if (! $session->get('mfa_code')) {
            $code = (string) random_int(100000, 999999);
            $session->set('mfa_code', $code);
            $session->set('mfa_expires', time() + 300); // 5 mins

            // Send notification through pluggable notifier
            auth()->getNotifier()->sendMfaCode($user, $code);

            // Trigger event for custom integrations
            Events::trigger('mfaChallenge', $user, $code);
        }

        $data = new AuthResponseData(
            action: 'action.show',
            status: 'info',
            statusCode: 200,
            message: 'A 2FA verification code has been sent to your email.',
            data: ['action' => 'email_2fa', 'email' => $user->getEmail()],
            user: $user
        );

        return auth()->renderResponse('action.show', $data, $request);
    }

    public function verify(RequestInterface $request, User $user): bool
    {
        $session = Services::session();
        $storedCode = (string) $session->get('mfa_code');
        $expires = (int) $session->get('mfa_expires');

        $input = $request->getJSON(true) ?? $request->getPost();
        $submittedCode = (string) ($input['code'] ?? '');

        if (! $storedCode || time() > $expires) {
            $session->remove(['mfa_code', 'mfa_expires']);
            return false;
        }

        if (hash_equals($storedCode, $submittedCode)) {
            $session->remove(['mfa_code', 'mfa_expires']);
            return true;
        }

        return false;
    }
}
