<?php

declare(strict_types=1);

namespace Jengo\Auth\Controllers;

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\ResponseInterface;
use Jengo\Auth\DTOs\AuthResponseData;
use Jengo\Auth\Forms\MagicLinkFormHandler;
use Jengo\Base\Attributes\Validate;

class MagicLinkController extends BaseAuthController
{
    public function showMagicLink(): ResponseInterface
    {
        if ($disabled = $this->ensureFeatureEnabled('allowMagicLink', 'magic_link')) {
            return $disabled;
        }

        $data = new AuthResponseData(
            action: 'magic_link.view',
            status: 'success',
            statusCode: 200,
            message: null
        );

        return $this->renderResponse('magic_link.view', $data);
    }

    #[Validate(MagicLinkFormHandler::class)]
    public function sendLink(): ResponseInterface
    {
        if ($disabled = $this->ensureFeatureEnabled('allowMagicLink', 'magic_link')) {
            return $disabled;
        }

        /** @var MagicLinkFormHandler $form */
        $form = form();
        $email = $form->getEmail();

        $auth = auth();
        $identity = $auth->getUserIdentityModel()->where(['type' => 'email_password', 'name' => $email])->first();

        // Always return success message to prevent user enumeration
        if ($identity) {
            $user = $auth->getUserModel()->find($identity->user_id);
            if ($user) {
                // Purge previous unused magic link tokens for this user
                $auth->getUserIdentityModel()
                    ->where('user_id', $user->id)
                    ->where('type', 'magic_link_token')
                    ->delete();

                $token = bin2hex(random_bytes(24));

                $auth->getUserIdentityModel()->insert([
                    'user_id' => $user->id,
                    'type'    => 'magic_link_token',
                    'name'    => 'magic_link',
                    'secret'  => hash('sha256', $token),
                    'expires' => date('Y-m-d H:i:s', time() + 900), // 15 mins
                ]);

                $verifyUrl = auth_url('magic-link.verify', $token);

                // Send magic link notification through pluggable notifier
                $auth->getNotifier()->sendMagicLink($user, $token, $verifyUrl);

                Events::trigger('magicLink', $user, $token, $verifyUrl);
            }
        }

        $data = new AuthResponseData(
            action: 'magic_link.sent',
            status: 'success',
            statusCode: 200,
            message: 'If the email exists, a magic login link has been sent.'
        );

        return $this->renderResponse('magic_link.sent', $data);
    }

    public function verifyLink(?string $token = null): ResponseInterface
    {
        if ($disabled = $this->ensureFeatureEnabled('allowMagicLink', 'magic_link')) {
            return $disabled;
        }

        $token = $token ?? $this->request->getGet('token');
        if (! $token) {
            return $this->notFoundResponse('magic_link.invalid');
        }

        $auth = auth();
        $tokenHash = hash('sha256', $token);

        $identity = $auth->getUserIdentityModel()
            ->where('type', 'magic_link_token')
            ->where('secret', $tokenHash)
            ->where('expires >=', date('Y-m-d H:i:s'))
            ->first();

        if (! $identity) {
            return $this->notFoundResponse('magic_link.invalid');
        }

        $user = $auth->getUserModel()->find($identity->user_id);
        if (! $user) {
            return $this->notFoundResponse('magic_link.invalid');
        }

        // Delete used token
        $auth->getUserIdentityModel()->delete($identity->id);

        // Login user
        $auth->login($user);
        Events::trigger('login', $user);

        $data = new AuthResponseData(
            action: 'magic_link.verified',
            status: 'success',
            statusCode: 200,
            message: 'Successfully logged in via magic link.',
            redirectTo: config('Auth')->redirects['home'] ?? '/dashboard',
            user: $user
        );

        return $this->renderResponse('magic_link.verified', $data);
    }
}
