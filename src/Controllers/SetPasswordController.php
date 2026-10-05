<?php

declare(strict_types=1);

namespace Jengo\Auth\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Jengo\Auth\DTOs\AuthResponseData;
use Jengo\Auth\Forms\SetPasswordFormHandler;
use Jengo\Base\Attributes\Validate;

class SetPasswordController extends BaseAuthController
{
    /**
     * Show Set Password form for OAuth user.
     */
    public function showSetPassword(): ResponseInterface
    {
        $user = auth()->currentUser();
        if ($user === null) {
            return redirect()->to(auth_url('login'));
        }

        $data = new AuthResponseData(
            action: 'set_password.view',
            status: 'success',
            statusCode: 200,
            message: null,
            user: $user,
            data: [
                'has_password' => $user->hasPassword(),
            ]
        );

        return $this->renderResponse('set_password.view', $data);
    }

    /**
     * Process setting a password for an OAuth user.
     */
    #[Validate(SetPasswordFormHandler::class)]
    public function attemptSetPassword(): ResponseInterface
    {
        $user = auth()->currentUser();
        if ($user === null) {
            return redirect()->to(auth_url('login'));
        }

        /** @var SetPasswordFormHandler $form */
        $form = form();
        $password = $form->getPassword();

        $user->setPassword($password);

        $data = new AuthResponseData(
            action: 'set_password.success',
            status: 'success',
            statusCode: 200,
            message: 'Password created successfully. You can now use email and password to log in.',
            user: $user,
            redirectTo: auth_redirect_url('login', '/dashboard')
        );

        return $this->renderResponse('set_password.success', $data);
    }
}
