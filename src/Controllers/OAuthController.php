<?php

declare(strict_types=1);

namespace Jengo\Auth\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Auth\DTOs\AuthResponseData;
use Throwable;

class OAuthController extends BaseAuthController
{
    /**
     * Redirect user to third-party OAuth provider authorization screen.
     */
    public function redirect(string $provider): ResponseInterface
    {
        $social = Services::social();

        if (!$social->isEnabled() || !$social->hasProvider($provider)) {
            return $this->handleError("Social authentication provider [{$provider}] is not enabled.", 404);
        }

        try {
            $driver = $social->driver($provider);
            $authUrl = $driver->getAuthUrl();

            return redirect()->to($authUrl);
        } catch (Throwable $e) {
            return $this->handleError($e->getMessage(), 400);
        }
    }

    /**
     * Handle incoming OAuth callback from provider.
     */
    public function callback(string $provider): ResponseInterface
    {
        $social = Services::social();

        if (!$social->isEnabled() || !$social->hasProvider($provider)) {
            return $this->handleError("Social authentication provider [{$provider}] is not enabled.", 404);
        }

        $queryParams = $this->request->getGet();

        try {
            // Check if user is already authenticated (Linking flow)
            if (auth()->check()) {
                $currentUser = auth()->currentUser();
                $driver = $social->driver($provider);
                $socialUser = $driver->handleCallback($queryParams);

                // Attach or update identity on the currently logged in user
                $identityModel = auth()->getUserIdentityModel();
                $identityType = 'oauth_' . $provider;
                $existing = $identityModel->where('type', $identityType)->where('name', $socialUser->id)->first();

                if ($existing !== null && (int) $existing->user_id !== (int) $currentUser->id) {
                    return $this->handleError("This {$provider} account is already linked to another user.", 422);
                }

                if ($existing !== null) {
                    $identityModel->update($existing->id, [
                        'secret'       => $socialUser->accessToken,
                        'secret2'      => $socialUser->avatar,
                        'extra'        => json_encode($socialUser->raw),
                        'last_used_at' => date('Y-m-d H:i:s'),
                    ]);
                } else {
                    $newIdentity = new \Jengo\Auth\Entities\UserIdentity([
                        'user_id'      => $currentUser->id,
                        'type'         => $identityType,
                        'name'         => $socialUser->id,
                        'secret'       => $socialUser->accessToken,
                        'secret2'      => $socialUser->avatar,
                        'extra'        => json_encode(array_merge($socialUser->raw, [
                            'email' => $socialUser->email,
                            'name'  => $socialUser->name,
                        ])),
                        'last_used_at' => date('Y-m-d H:i:s'),
                    ]);
                    $identityModel->insert($newIdentity);
                }

                \CodeIgniter\Events\Events::trigger('socialLinked', $currentUser, $provider, $socialUser);

                $identitiesUrl = function_exists('auth_url') ? auth_url('identities.index') : '/identities';
                $data = new AuthResponseData(
                    action: 'social.linked',
                    status: 'success',
                    statusCode: 200,
                    message: "Successfully linked your " . ucfirst($provider) . " account.",
                    user: $currentUser,
                    redirectTo: $identitiesUrl
                );

                return $this->renderResponse('social.linked', $data);
            }

            // Find or create user via SocialManager
            $user = $social->handleCallback($provider, $queryParams);

            // Log user into session guard
            auth()->guard('session')->login($user);

            // Check if post-auth actions (MFA / terms / etc.) are configured for login
            if (auth()->hasPendingActions()) {
                $actionData = new AuthResponseData(
                    action: 'login.action_required',
                    status: 'info',
                    statusCode: 200,
                    message: 'Additional verification required.',
                    user: $user,
                    redirectTo: auth_url('auth.action.show')
                );

                return $this->renderResponse('login.action_required', $actionData);
            }

            $landingUrl = auth_redirect_url('login', '/');

            $data = new AuthResponseData(
                action: 'social.login',
                status: 'success',
                statusCode: 200,
                message: "Successfully signed in with " . ucfirst($provider) . ".",
                user: $user,
                redirectTo: $landingUrl
            );

            return $this->renderResponse('social.login', $data);
        } catch (Throwable $e) {
            return $this->handleError($e->getMessage(), 400);
        }
    }

    /**
     * Render error response cleanly via UniversalModifier / Flash session.
     */
    protected function handleError(string $message, int $statusCode): ResponseInterface
    {
        $data = new AuthResponseData(
            action: 'social.error',
            status: 'error',
            statusCode: $statusCode,
            message: $message,
            errors: ['oauth' => $message],
            redirectTo: auth_url('login')
        );

        return $this->renderResponse('social.error', $data);
    }
}
