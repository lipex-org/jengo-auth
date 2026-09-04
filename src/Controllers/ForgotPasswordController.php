<?php

declare(strict_types=1);

namespace Jengo\Auth\Controllers;

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\ResponseInterface;
use Jengo\Auth\DTOs\AuthResponseData;
use Jengo\Auth\Forms\ForgotPasswordFormHandler;
use Jengo\Base\Attributes\Validate;

class ForgotPasswordController extends BaseAuthController
{
    public function showForgot(): ResponseInterface
    {
        if ($disabled = $this->ensureFeatureEnabled('allowPasswordReset', 'forgot_password')) {
            return $disabled;
        }

        $data = new AuthResponseData(
            action: 'forgot_password.view',
            status: 'success',
            statusCode: 200,
            message: null
        );

        return $this->renderResponse('forgot_password.view', $data);
    }

    #[Validate(ForgotPasswordFormHandler::class)]
    public function sendResetLink(): ResponseInterface
    {
        if ($disabled = $this->ensureFeatureEnabled('allowPasswordReset', 'forgot_password')) {
            return $disabled;
        }

        /** @var ForgotPasswordFormHandler $form */
        $form = form();
        $email = $form->getEmail();

        $auth = auth();
        $identity = $auth->getUserIdentityModel()->where(['type' => 'email_password', 'name' => $email])->first();

        // Always return success message to prevent user enumeration
        if ($identity) {
            $user = $auth->getUserModel()->find($identity->user_id);
            if ($user) {
                // Purge any existing unused password reset tokens for this user
                $auth->getUserIdentityModel()
                    ->where('user_id', $user->id)
                    ->where('type', 'password_reset_token')
                    ->delete();

                $token = bin2hex(random_bytes(20));

                // Store fresh password reset token identity
                $auth->getUserIdentityModel()->insert([
                    'user_id' => $user->id,
                    'type'    => 'password_reset_token',
                    'name'    => 'password_reset',
                    'secret'  => hash('sha256', $token),
                    'expires' => date('Y-m-d H:i:s', time() + 3600),
                ]);

                $resetUrl = auth_url('reset-password', $token);

                // Send notification through pluggable notifier
                $auth->getNotifier()->sendPasswordReset($user, $token, $resetUrl);

                Events::trigger('forgotPassword', $user, $token, $resetUrl);
            }
        }

        $data = new AuthResponseData(
            action: 'forgot_password.sent',
            status: 'success',
            statusCode: 200,
            message: 'If the email exists in our system, a password reset link has been sent.'
        );

        return $this->renderResponse('forgot_password.sent', $data);
    }
}
