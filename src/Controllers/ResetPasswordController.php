<?php

declare(strict_types=1);

namespace Jengo\Auth\Controllers;

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\ResponseInterface;
use Jengo\Auth\DTOs\AuthResponseData;

class ResetPasswordController extends BaseAuthController
{
    public function showReset(?string $token = null): ResponseInterface
    {
        if ($disabled = $this->ensureFeatureEnabled('allowPasswordReset', 'reset_password')) {
            return $disabled;
        }

        $token = $token ?? $this->request->getGet('token');
        if (! $token) {
            return $this->notFoundResponse('reset_password.invalid_token');
        }

        $data = new AuthResponseData(
            action: 'reset_password.view',
            status: 'success',
            statusCode: 200,
            data: ['token' => $token]
        );

        return $this->renderResponse('reset_password.view', $data);
    }

    public function attemptReset(): ResponseInterface
    {
        if ($disabled = $this->ensureFeatureEnabled('allowPasswordReset', 'reset_password')) {
            return $disabled;
        }

        $payload = $this->extractPayload();
        $token = $payload['token'] ?? null;
        $password = $payload['password'] ?? null;
        $passwordConfirm = $payload['password_confirm'] ?? null;

        if (! $token || ! $password || strlen($password) < 8 || ($passwordConfirm !== null && $password !== $passwordConfirm)) {
            $data = new AuthResponseData(
                action: 'reset_password.validation_failed',
                status: 'error',
                statusCode: 422,
                message: 'Invalid password or mismatched confirmation.',
                errors: ['password' => 'Password must be at least 8 characters and match confirmation.']
            );
            return $this->renderResponse('reset_password.validation_failed', $data);
        }

        $auth = auth();
        $tokenHash = hash('sha256', $token);

        $identity = $auth->getUserIdentityModel()
            ->where('type', 'password_reset_token')
            ->where('secret', $tokenHash)
            ->where('expires >=', date('Y-m-d H:i:s'))
            ->first();

        if (! $identity) {
            // Mask invalid/expired tokens as 404
            return $this->notFoundResponse('reset_password.invalid_token');
        }

        $user = $auth->getUserModel()->find($identity->user_id);
        if (! $user) {
            return $this->notFoundResponse('reset_password.invalid_user');
        }

        // Update primary email password identity
        $emailIdentity = $auth->getUserIdentityModel()->where(['user_id' => $user->id, 'type' => 'email_password'])->first();
        if ($emailIdentity) {
            $auth->getUserIdentityModel()->update($emailIdentity->id, [
                'secret' => $auth->getHasher()->hash($password),
            ]);
        }

        // Remove the used reset token
        $auth->getUserIdentityModel()->delete($identity->id);

        Events::trigger('passwordReset', $user);

        $data = new AuthResponseData(
            action: 'reset_password.success',
            status: 'success',
            statusCode: 200,
            message: 'Password reset successfully. You can now log in.',
            redirectTo: config('Auth')->redirects['login'] ?? '/login',
            user: $user
        );

        return $this->renderResponse('reset_password.success', $data);
    }
}
