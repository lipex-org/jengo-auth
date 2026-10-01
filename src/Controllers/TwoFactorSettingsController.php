<?php

declare(strict_types=1);

namespace Jengo\Auth\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Auth\DTOs\AuthResponseData;

class TwoFactorSettingsController extends BaseAuthController
{
    /**
     * List user's enrolled 2FA factors and available enrollment options.
     */
    public function index(): ResponseInterface
    {
        $auth = Services::auth();
        if (!$auth->check()) {
            $data = new AuthResponseData(
                action: 'two_factor.index',
                status: 'error',
                statusCode: 401,
                message: 'Unauthenticated.',
                redirectTo: config('Auth')->redirects['login'] ?? '/login'
            );
            return $this->renderResponse('two_factor.index', $data);
        }

        $user = $auth->user();
        $twoFactor = Services::twoFactor();

        $enrolled = $twoFactor->getEnrolledFactorsSummary($user);

        $available = [];
        foreach ($twoFactor->drivers() as $id => $driver) {
            $available[] = [
                'id'          => $driver->getId(),
                'label'       => $driver->getLabel(),
                'icon'        => $driver->getIcon(),
                'description' => $driver->getDescription(),
                'is_enrolled' => $driver->isEnrolled($user),
            ];
        }

        $data = new AuthResponseData(
            action: 'two_factor.index',
            status: 'success',
            statusCode: 200,
            data: [
                'enrolled_factors'  => $enrolled,
                'available_factors' => $available,
                'enrolledFactors'   => $enrolled,
                'availableFactors'  => $available,
            ],
            user: $user
        );

        return $this->renderResponse('two_factor.index', $data);
    }

    /**
     * Start enrolling a new factor (e.g. generate TOTP secret / Passkey options).
     */
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

        $input = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
        $driverId = (string) ($input['factor'] ?? '');

        $twoFactor = Services::twoFactor();

        try {
            $result = $twoFactor->startEnrollment($auth->user(), $driverId, $input['options'] ?? []);

            $data = new AuthResponseData(
                action: 'two_factor.enroll.start',
                status: 'success',
                statusCode: 200,
                data: [
                    'factor' => $driverId,
                    'data'   => $result,
                ],
                user: $auth->user()
            );
            return $this->renderResponse('two_factor.enroll.start', $data);
        } catch (\Throwable $e) {
            $data = new AuthResponseData(
                action: 'two_factor.enroll.start',
                status: 'error',
                statusCode: 400,
                message: $e->getMessage()
            );
            return $this->renderResponse('two_factor.enroll.start', $data);
        }
    }

    /**
     * Confirm enrollment with initial verification proof.
     */
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

        $input = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
        $driverId = (string) ($input['factor'] ?? '');
        $proof = $input['proof'] ?? $input['code'] ?? null;
        $metadata = $input['metadata'] ?? [];

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

        $input = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
        $driverId = (string) ($input['factor'] ?? '');
        $credentialId = $input['credential_id'] ?? null;

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

