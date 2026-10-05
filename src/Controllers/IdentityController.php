<?php

declare(strict_types=1);

namespace Jengo\Auth\Controllers;

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Auth\DTOs\AuthResponseData;
use Jengo\Auth\Forms\IdentityUnlinkFormHandler;
use Jengo\Base\Attributes\Validate;

class IdentityController extends BaseAuthController
{
    /**
     * List all authentication identities linked to the authenticated user.
     */
    public function showIdentities(): ResponseInterface
    {
        $auth = Services::auth();
        if (!$auth->check()) {
            $data = new AuthResponseData(
                action: 'identities.index',
                status: 'error',
                statusCode: 401,
                message: 'Unauthenticated.',
                redirectTo: auth_redirect_url('login', 'login')
            );
            return $this->renderResponse('identities.index', $data);
        }

        $user = $auth->currentUser();
        $social = Services::social();

        // 1. Password identity
        $hasPassword = $user->hasPassword();

        // 2. Linked OAuth / Social identities
        $identities = $user->getIdentitiesSummary();

        // 3. Passkeys
        $passkeys = $user->passkeys();

        // 4. Available social providers to link
        $availableProviders = $social->getAvailableProviders();

        $data = new AuthResponseData(
            action: 'identities.index',
            status: 'success',
            statusCode: 200,
            data: [
                'has_password'        => $hasPassword,
                'identities'          => $identities,
                'passkeys_count'      => count($passkeys),
                'available_providers' => $availableProviders,
            ],
            user: $user
        );

        return $this->renderResponse('identities.index', $data);
    }

    /**
     * Unlink a third-party social OAuth identity from the authenticated user's account.
     *
     * @param string|null $identityId Optional route parameter ID or provider
     */
    #[Validate(IdentityUnlinkFormHandler::class)]
    public function unlinkIdentity(?string $identityId = null): ResponseInterface
    {
        $auth = Services::auth();
        if (!$auth->check()) {
            $data = new AuthResponseData(
                action: 'identities.unlink',
                status: 'error',
                statusCode: 401,
                message: 'Unauthenticated.',
                redirectTo: auth_redirect_url('login', 'login')
            );
            return $this->renderResponse('identities.unlink', $data);
        }

        /** @var IdentityUnlinkFormHandler $form */
        $form = form();
        $targetId = $identityId ?? $form->getIdentityId() ?? $this->request->getPost('identity_id');
        $provider = $form->getProvider() ?? $this->request->getPost('provider');

        $user = $auth->currentUser();
        $identityModel = $auth->getUserIdentityModel();

        // Find the identity to delete
        $query = $identityModel->where('user_id', $user->id);

        if (!empty($targetId) && is_numeric($targetId)) {
            $query->where('id', (int) $targetId);
        } elseif (!empty($provider)) {
            $query->where('type', 'oauth_' . $provider);
        } elseif (!empty($targetId)) {
            // Could be provider name passed as segment
            $query->where('type', 'oauth_' . $targetId);
        } else {
            $data = new AuthResponseData(
                action: 'identities.unlink',
                status: 'error',
                statusCode: 422,
                message: 'No identity specified to unlink.'
            );
            return $this->renderResponse('identities.unlink', $data);
        }

        $identity = $query->first();

        if ($identity === null) {
            $data = new AuthResponseData(
                action: 'identities.unlink',
                status: 'error',
                statusCode: 404,
                message: 'Identity not found.'
            );
            return $this->renderResponse('identities.unlink', $data);
        }

        // Prevent unlinking if it's the user's only login method
        $allIdentities = $user->getIdentitiesSummary();
        $hasPassword = $user->hasPassword();
        $passkeysCount = count($user->passkeys());

        $otherIdentitiesCount = count(array_filter($allIdentities, fn($i) => (int) $i['id'] !== (int) $identity->id));

        if (!$hasPassword && $otherIdentitiesCount === 0 && $passkeysCount === 0) {
            $data = new AuthResponseData(
                action: 'identities.unlink',
                status: 'error',
                statusCode: 422,
                message: 'Cannot unlink your only sign-in method. Please set a password first.',
                errors: ['identity' => 'Cannot unlink your only sign-in method. Please set a password first.']
            );
            return $this->renderResponse('identities.unlink', $data);
        }

        $identityModel->delete($identity->id);

        $providerName = str_starts_with($identity->type, 'oauth_') ? substr($identity->type, 6) : $identity->type;
        Events::trigger('socialUnlinked', $user, $providerName, $identity);

        $data = new AuthResponseData(
            action: 'identities.unlink',
            status: 'success',
            statusCode: 200,
            message: 'Identity unlinked successfully.',
            redirectTo: auth_url('identities.index'),
            user: $user
        );

        return $this->renderResponse('identities.unlink', $data);
    }
}
