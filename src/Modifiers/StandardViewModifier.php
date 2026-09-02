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

        // 2. GET / View actions (ending in .view or explicit view provided)
        if ($data->view !== null || str_ends_with($action, '.view') || str_ends_with($action, '.show')) {
            $viewName = $data->view ?? $this->resolveViewForAction($action);
            if ($viewName && (file_exists(APPPATH . 'Views/' . $viewName . '.php') || function_exists('view'))) {
                try {
                    $html = view($viewName, array_merge($data->data, [
                        'user'    => $data->user,
                        'message' => $data->message,
                        'errors'  => $data->errors,
                    ]));
                    return $response->setStatusCode($data->statusCode)->setBody($html);
                } catch (\Throwable $e) {
                    // Fallback to basic HTML if view template not found
                }
            }

            return $response->setStatusCode($data->statusCode)->setBody("Auth View [{$action}]");
        }

        // 3. Mutation Success Actions -> Redirect
        $redirectUrl = $data->redirectTo ?? config('Auth')->redirects['home'] ?? '/dashboard';

        $redirect = redirect()->to($redirectUrl);
        if ($data->message) {
            $redirect = $redirect->with('message', $data->message);
        }

        return $redirect;
    }

    protected function resolveViewForAction(string $action): ?string
    {
        $config = config('Auth');
        $views = $config->views ?? [];

        return match ($action) {
            'login.view'          => $views['login'] ?? 'Jengo\Auth\Views\login',
            'register.view'       => $views['register'] ?? 'Jengo\Auth\Views\register',
            'forgot_password.view'=> $views['forgotPassword'] ?? 'Jengo\Auth\Views\forgot_password',
            'reset_password.view' => $views['resetPassword'] ?? 'Jengo\Auth\Views\reset_password',
            'magic_link.view'     => $views['magicLink'] ?? 'Jengo\Auth\Views\magic_link',
            'magic_link.sent'     => $views['magicLinkSent'] ?? 'Jengo\Auth\Views\magic_link_sent',
            'action.show'         => $views['action_mfa'] ?? 'Jengo\Auth\Views\mfa_challenge',
            default               => null,
        };
    }
}
