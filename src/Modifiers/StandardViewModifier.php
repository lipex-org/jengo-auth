<?php

declare(strict_types=1);

namespace Jengo\Auth\Modifiers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Auth\Contracts\ResponseModifierInterface;
use Jengo\Auth\DTOs\AuthResponseData;

class StandardViewModifier implements ResponseModifierInterface
{
    public function modify(string $action, AuthResponseData $data, RequestInterface $request): ResponseInterface
    {
        $response = Services::response();

        // 1. Error responses
        if (! $data->isSuccess()) {
            if ($data->statusCode === 404) {
                return $response->setStatusCode(404)->setBody('Page Not Found');
            }

            if ($data->statusCode === 403) {
                return $response->setStatusCode(403)->setBody($data->message ?? 'Forbidden');
            }

            // Redirect back with validation errors or flash error
            $redirect = redirect()->back()->withInput();
            if (! empty($data->errors)) {
                $redirect = $redirect->with('errors', $data->errors);
            }

            if ($data->message) {
                $redirect = $redirect->with('error', $data->message);
            }

            return $redirect;
        }

        // 2. GET / View actions (explicit view set, matching resolved view, or action naming convention)
        $viewName = $data->view ?? $this->resolveViewForAction($action, $data->data);

        if ($viewName !== null || str_ends_with($action, '.view') || str_ends_with($action, '.show') || str_ends_with($action, '.index')) {
            if ($viewName && function_exists('view')) {
                try {
                    $authConfig = function_exists('config') ? config('Auth') : null;
                    $branding = $authConfig->branding ?? [];
                    $brand = [
                        'name'        => $branding['name'] ?? $authConfig->brandName ?? 'Jengo',
                        'logo'        => $branding['logo'] ?? $authConfig->brandLogo ?? null,
                        'companyName' => $branding['companyName'] ?? $authConfig->companyName ?? null,
                    ];
                    $html = view($viewName, array_merge($data->data, [
                        'user'    => $data->user,
                        'message' => $data->message,
                        'errors'  => $data->errors,
                        'brand'   => $brand,
                    ]));
                    return $response->setStatusCode($data->statusCode)->setBody($html);
                } catch (\Throwable $e) {
                    // Fallback to basic HTML if view template not found
                }
            }

            if ($viewName) {
                return $response->setStatusCode($data->statusCode)->setBody("Auth View [{$viewName}]");
            }
        }

        // 3. Mutation API/JSON actions without redirect
        if ($data->redirectTo === null && ! empty($data->data)) {
            return $response->setStatusCode($data->statusCode)->setJSON(array_merge([
                'action'  => $action,
                'status'  => $data->status,
                'message' => $data->message,
            ], $data->data));
        }

        // 3. Mutation Success Actions -> Redirect
        $redirectUrl = $data->redirectTo ?? (function_exists('auth_redirect_url') ? auth_redirect_url('login', '/') : (config('Auth')->redirects['login'] ?? '/'));

        $redirect = redirect()->to($redirectUrl);
        if ($data->message) {
            $redirect = $redirect->with('message', $data->message);
        }

        if(!empty($data->flash) && is_array($data->flash)) {
            foreach($data->flash as $key => $value) {
                $redirect = $redirect->with($key, $value);
            }
        }

        return $redirect;
    }

    protected function resolveViewForAction(string $action, array $data = []): ?string
    {
        $config = config('Auth');
        $views = $config->views ?? [];

        if ($action === 'action.show') {
            $actionName = $data['action'] ?? null;
            if ($actionName) {
                // Check action-specific view keys: e.g. 'action_email_2fa', 'action_mfa_email_2fa'
                if (! empty($views['action_' . $actionName])) {
                    return $views['action_' . $actionName];
                }
                if (! empty($views['action_mfa_' . $actionName])) {
                    return $views['action_mfa_' . $actionName];
                }
            }
            return $views['action_mfa'] ?? 'Jengo\Auth\Views\mfa_challenge';
        }

        return match ($action) {
            'login.view'          => $views['login'] ?? 'Jengo\Auth\Views\login',
            'register.view'       => $views['register'] ?? 'Jengo\Auth\Views\register',
            'forgot_password.view'=> $views['forgotPassword'] ?? 'Jengo\Auth\Views\forgot_password',
            'reset_password.view' => $views['resetPassword'] ?? 'Jengo\Auth\Views\reset_password',
            'magic_link.view'     => $views['magicLink'] ?? 'Jengo\Auth\Views\magic_link',
            'sudo.view', 'auth.sudo' => $views['sudo'] ?? 'Jengo\Auth\Views\sudo_challenge',
            'two_factor.view', 'two_factor.index' => $views['two_factor_settings'] ?? 'Jengo\\Auth\\Views\\two_factor_settings',
            'tokens.list', 'tokens.index', 'tokens.view' => $views['tokens'] ?? 'Jengo\\Auth\\Views\\tokens_index',
            'set_password.view'   => $views['set_password'] ?? 'Jengo\\Auth\\Views\\set_password',
            'identities.view', 'identities.index' => $views['identities'] ?? 'Jengo\\Auth\\Views\\identities',
            default               => null,
        };
    }
    public function modifyValidationFailed(array $errors, RequestInterface $request, array $options = []): ResponseInterface
    {
        return redirect()->back()->withInput()->with('errors', $errors);
    }
}
