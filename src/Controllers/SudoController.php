<?php

declare(strict_types=1);

namespace Jengo\Auth\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Auth\DTOs\AuthResponseData;
use Jengo\Auth\Forms\SudoChallengeFormHandler;
use Jengo\Auth\Forms\SudoVerifyFormHandler;
use Jengo\Auth\Sudo\SudoManager;
use Jengo\Base\Attributes\Validate;

class SudoController extends BaseAuthController
{
    /**
     * Display Sudo challenge UI or list available verification factors.
     */
    public function index(): ResponseInterface
    {
        $auth = Services::auth();
        if (!$auth->check()) {
            $data = new AuthResponseData(
                action: 'sudo.view',
                status: 'error',
                statusCode: 401,
                message: 'Unauthenticated.',
                redirectTo: config('Auth')->redirects['login'] ?? '/login'
            );
            return $this->renderResponse('sudo.view', $data);
        }

        $user = $auth->user();
        $sudo = Services::sudo();

        // If already in sudo mode, redirect to intended or home
        if ($sudo->check()) {
            $intended = session()->get(SudoManager::SESSION_INTENDED) ?? config('Auth')->redirects['home'] ?? '/';
            session()->remove(SudoManager::SESSION_INTENDED);

            $data = new AuthResponseData(
                action: 'sudo.view',
                status: 'success',
                statusCode: 200,
                message: 'Sudo mode already active.',
                redirectTo: $intended,
                user: $user
            );
            return $this->renderResponse('sudo.view', $data);
        }

        $twoFactor = Services::twoFactor();
        $availableFactors = $twoFactor->getEnrolledFactorsSummary($user);

        $data = new AuthResponseData(
            action: 'sudo.view',
            status: 'success',
            statusCode: 200,
            data: [
                'sudo_active'       => false,
                'available_factors' => $availableFactors,
                'availableFactors'  => $availableFactors,
            ],
            user: $user
        );

        return $this->renderResponse('sudo.view', $data);
    }

    /**
     * Request a challenge payload for a specific factor (e.g. Passkey options or sending Email OTP).
     */
    #[Validate(SudoChallengeFormHandler::class)]
    public function challenge(): ResponseInterface
    {
        $auth = Services::auth();
        if (!$auth->check()) {
            $data = new AuthResponseData(
                action: 'sudo.challenge',
                status: 'error',
                statusCode: 401,
                message: 'Unauthenticated.'
            );
            return $this->renderResponse('sudo.challenge', $data);
        }

        /** @var SudoChallengeFormHandler $form */
        $form = form();
        $driverId = $form->getFactor();
        $twoFactor = Services::twoFactor();

        if (!$twoFactor->hasDriver($driverId)) {
            $data = new AuthResponseData(
                action: 'sudo.challenge',
                status: 'error',
                statusCode: 400,
                message: "Invalid factor [{$driverId}]."
            );
            return $this->renderResponse('sudo.challenge', $data);
        }

        $challenge = $twoFactor->createChallenge($auth->user(), $driverId);

        $data = new AuthResponseData(
            action: 'sudo.challenge',
            status: 'success',
            statusCode: 200,
            data: [
                'challenge' => $challenge,
            ],
            user: $auth->user()
        );

        return $this->renderResponse('sudo.challenge', $data);
    }

    /**
     * Verify submitted factor proof and enter Sudo mode.
     */
    #[Validate(SudoVerifyFormHandler::class)]
    public function verify(): ResponseInterface
    {
        $auth = Services::auth();
        if (!$auth->check()) {
            $data = new AuthResponseData(
                action: 'sudo.verify',
                status: 'error',
                statusCode: 401,
                message: 'Unauthenticated.'
            );
            return $this->renderResponse('sudo.verify', $data);
        }

        /** @var SudoVerifyFormHandler $form */
        $form = form();
        $driverId = $form->getFactor();
        $proof = $form->getProof();
        $lifetime = $form->getLifetime();

        $user = $auth->user();
        $sudo = Services::sudo();

        if (!$driverId || $proof === null) {
            $data = new AuthResponseData(
                action: 'sudo.verify',
                status: 'error',
                statusCode: 422,
                message: 'Factor and verification proof are required.',
                errors: ['factor' => 'Factor and verification proof are required.']
            );
            return $this->renderResponse('sudo.verify', $data);
        }

        $verified = $sudo->verifyAndActivate($user, $driverId, $proof, $lifetime);

        if (!$verified) {
            $data = new AuthResponseData(
                action: 'sudo.verify',
                status: 'error',
                statusCode: 422,
                message: 'Verification failed. Please check your credentials and try again.',
                errors: ['credentials' => 'Verification failed. Please check your credentials and try again.']
            );
            return $this->renderResponse('sudo.verify', $data);
        }

        $intended = session()->get(SudoManager::SESSION_INTENDED) ?? config('Auth')->redirects['home'] ?? '/';
        session()->remove(SudoManager::SESSION_INTENDED);

        $data = new AuthResponseData(
            action: 'sudo.verified',
            status: 'success',
            statusCode: 200,
            message: 'Identity verified. Sudo mode activated.',
            data: [
                'intended_url' => $intended,
                'expires_at'   => $sudo->expiresAt(),
                'expires_in'   => $sudo->secondsRemaining(),
            ],
            redirectTo: $intended,
            user: $user
        );

        return $this->renderResponse('sudo.verified', $data);
    }

    /**
     * Exit Sudo mode.
     */
    public function exit(): ResponseInterface
    {
        Services::sudo()->deactivate();

        $data = new AuthResponseData(
            action: 'sudo.exit',
            status: 'success',
            statusCode: 200,
            message: 'Sudo mode exited.',
            redirectTo: config('Auth')->redirects['home'] ?? '/'
        );

        return $this->renderResponse('sudo.exit', $data);
    }
}
