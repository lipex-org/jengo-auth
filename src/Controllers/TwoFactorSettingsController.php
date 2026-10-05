<?php

declare(strict_types=1);

namespace Jengo\Auth\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Auth\DTOs\AuthResponseData;
use Jengo\Auth\Forms\TwoFactorEnrollConfirmFormHandler;
use Jengo\Auth\Forms\TwoFactorEnrollStartFormHandler;
use Jengo\Auth\Forms\TwoFactorUnenrollFormHandler;
use Jengo\Base\Attributes\Validate;

class TwoFactorSettingsController extends BaseAuthController
{
    /**
     * List user's enrolled 2FA factors and available enrollment options.
     */
    public function index(): ResponseInterface
    {
        $auth = Services::auth();
        helper("Jengo\Base\Helpers\jengo");
        if (!$auth->check()) {
            $data = new AuthResponseData(
                action: 'two_factor.index',
                status: 'error',
                statusCode: 401,
                message: 'Unauthenticated.',
                redirectTo: auth_redirect_url('login', 'login')
            );
            return $this->renderResponse('two_factor.index', $data);
        }

        $user = $auth->user();
        $twoFactor = Services::twoFactor();

        $enrolled = $twoFactor->getEnrolledFactorsSummary($user);

        $available = [];
        foreach ($twoFactor->drivers() as $id => $driver) {
            $available[] = [
                'id' => $driver->getId(),
                'label' => $driver->getLabel(),
                'icon' => $driver->getIcon(),
                'description' => $driver->getDescription(),
                'is_enrolled' => $driver->isEnrolled($user)
            ];
        }

        $data = new AuthResponseData(
            action: 'two_factor.index',
            status: 'success',
            statusCode: 200,
            data: [
                'enrolled_factors' => $enrolled,
                'available_factors' => $available,
            ],
            user: $user
        );

        return $this->renderResponse('two_factor.index', $data);
    }

    /**
     * Start enrolling a new factor (e.g. generate TOTP secret / Passkey options).
     */
    #[Validate(TwoFactorEnrollStartFormHandler::class)]
    public function startEnrollment(): ResponseInterface
    {
        $auth = Services::auth();
        if (!$auth->check()) {
            $data = new AuthResponseData(
                action: 'two_factor.enroll.start',
                status: 'error',
                statusCode: 401,
                message: 'Unauthenticated.'
            );
            return $this->renderResponse('two_factor.enroll.start', $data);
        }

        /** @var TwoFactorEnrollStartFormHandler $form */
        $form = form();
        $driverId = $form->getFactor();
        $options = $form->getOptions();

        $twoFactor = Services::twoFactor();

        try {
            $result = $twoFactor->startEnrollment($auth->user(), $driverId, $options);

            $data = new AuthResponseData(
                action: 'two_factor.enroll.start',
                status: 'success',
                statusCode: 200,
                data: [
                    'factor' => $driverId,
                    'data' => $result,
                ],
                flash: [
                    'enrollment_factor' => $driverId,
                    'enrollment_data'   => $result,
                ],
                user: $auth->user(),
                redirectTo: auth_url('two-factor.index')
            );
            return $this->renderResponse('two_factor.enroll.start', $data);
        } catch (\Throwable $e) {
            $data = new AuthResponseData(
                action: 'two_factor.enroll.start',
                status: 'error',
                statusCode: 400,
                message: $e->getMessage(),
                redirectTo: auth_url('two-factor.index')
            );
            return $this->renderResponse('two_factor.enroll.start', $data);
        }
    }

    /**
     * Confirm enrollment with initial verification proof.
     */
    #[Validate(TwoFactorEnrollConfirmFormHandler::class)]
    public function confirmEnrollment(): ResponseInterface
    {
        $auth = Services::auth();
        if (!$auth->check()) {
            $data = new AuthResponseData(
                action: 'two_factor.enroll.confirm',
                status: 'error',
                statusCode: 401,
                message: 'Unauthenticated.'
            );
            return $this->renderResponse('two_factor.enroll.confirm', $data);
        }

        /** @var TwoFactorEnrollConfirmFormHandler $form */
        $form = form();
        $driverId = $form->getFactor();
        $proof = $form->getProof();
        $metadata = $form->getMetadata();

        $twoFactor = Services::twoFactor();

        try {
            $confirmed = $twoFactor->confirmEnrollment($auth->user(), $driverId, $proof, $metadata);
            if (!$confirmed) {
                $data = new AuthResponseData(
                    action: 'two_factor.enroll.confirm',
                    status: 'error',
                    statusCode: 422,
                    message: 'Verification failed. Factor was not enrolled.',
                    errors: ['proof' => 'Verification failed. Factor was not enrolled.']
                );
                return $this->renderResponse('two_factor.enroll.confirm', $data);
            }

            $data = new AuthResponseData(
                action: 'two_factor.enroll.confirm',
                status: 'success',
                statusCode: 200,
                message: 'Two-factor method successfully enrolled.',
                redirectTo: auth_url('two-factor.index'),
                user: $auth->user()
            );
            return $this->renderResponse('two_factor.enroll.confirm', $data);
        } catch (\Throwable $e) {
            $data = new AuthResponseData(
                action: 'two_factor.enroll.confirm',
                status: 'error',
                statusCode: 400,
                message: $e->getMessage()
            );
            return $this->renderResponse('two_factor.enroll.confirm', $data);
        }
    }

    /**
     * Unenroll / remove a two-factor method.
     */
    #[Validate(TwoFactorUnenrollFormHandler::class)]
    public function unenroll(): ResponseInterface
    {
        $auth = Services::auth();
        if (!$auth->check()) {
            $data = new AuthResponseData(
                action: 'two_factor.unenroll',
                status: 'error',
                statusCode: 401,
                message: 'Unauthenticated.'
            );
            return $this->renderResponse('two_factor.unenroll', $data);
        }

        /** @var TwoFactorUnenrollFormHandler $form */
        $form = form();
        $driverId = $form->getFactor();
        $credentialId = $form->getCredentialId();

        $twoFactor = Services::twoFactor();

        try {
            $twoFactor->unenroll($auth->user(), $driverId, $credentialId);

            $data = new AuthResponseData(
                action: 'two_factor.unenroll',
                status: 'success',
                statusCode: 200,
                message: 'Two-factor method removed.',
                redirectTo: auth_url('two-factor.index'),
                user: $auth->user()
            );
            return $this->renderResponse('two_factor.unenroll', $data);
        } catch (\Throwable $e) {
            $data = new AuthResponseData(
                action: 'two_factor.unenroll',
                status: 'error',
                statusCode: 400,
                message: $e->getMessage()
            );
            return $this->renderResponse('two_factor.unenroll', $data);
        }
    }
}

