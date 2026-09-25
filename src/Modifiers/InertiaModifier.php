<?php

declare(strict_types=1);

namespace Jengo\Auth\Modifiers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Auth\Contracts\ResponseModifierInterface;
use Jengo\Auth\DTOs\AuthResponseData;
use Jengo\Inertia\Inertia;
class InertiaModifier implements ResponseModifierInterface
{
    public function modify(string $action, AuthResponseData $data, RequestInterface $request): ResponseInterface
    {
        // 1. Errors
        if (! $data->isSuccess()) {
            if ($data->statusCode === 404) {
                return Services::response()->setStatusCode(404)->setBody('Page Not Found');
            }

            if ($data->statusCode === 403) {
                return Services::response()->setStatusCode(403)->setBody($data->message ?? 'Forbidden');
            }

            if ($data->errors) {
                session()->setFlashdata('errors', $data->errors);
            }
            if ($data->message) {
                session()->setFlashdata('error', $data->message);
            }

            return redirect()->back()->withInput()->with('errors', $data->errors)->with('error', $data->message);
        }

        // 2. Views / GET actions
        if ($data->view !== null || str_ends_with($action, '.view') || str_ends_with($action, '.show')) {
            if (! class_exists(Inertia::class)) {
                throw new \RuntimeException(
                    'The jengo/inertia package is required to use InertiaModifier. Run: composer require jengo/inertia'
                );
            }

            $component = $this->resolveComponentForAction($action, $data->view);
            $props = $data->toArray();

            return Inertia::render($component, $props);
        }

        // 3. Success Redirect
        $redirectUrl = $data->redirectTo ?? config('Auth')->redirects['home'] ?? '/dashboard';
        if ($data->message) {
            session()->setFlashdata('message', $data->message);
        }

        return redirect()->to($redirectUrl)->with('message', $data->message);
    }

    protected function resolveComponentForAction(string $action, ?string $customView = null): string
    {
        if ($customView !== null) {
            return $customView;
        }

        $config = config('Auth');
        $views = $config->views ?? [];

        $configuredView = match ($action) {
            'login.view'          => $views['login'] ?? null,
            'register.view'       => $views['register'] ?? null,
            'forgot_password.view'=> $views['forgotPassword'] ?? null,
            'reset_password.view' => $views['resetPassword'] ?? null,
            'magic_link.view'     => $views['magicLink'] ?? null,
            'magic_link.sent'     => $views['magicLinkSent'] ?? null,
            'action.show'         => $views['action_mfa'] ?? null,
            default               => null,
        };

        if ($configuredView !== null) {
            // If configured with a custom Inertia component path (e.g. 'Auth/CustomLogin' or 'Pages/Login')
            if (! str_starts_with($configuredView, 'Jengo\\Auth\\Views\\')) {
                return $configuredView;
            }

            // If configured with a default standard PHP view namespace path, convert cleanly to Inertia component format
            $base = basename(str_replace('\\', '/', $configuredView));
            return 'Auth/' . ucfirst(str_replace('_', '', ucwords($base, '_')));
        }

        return match ($action) {
            'login.view'          => 'Auth/Login',
            'register.view'       => 'Auth/Register',
            'forgot_password.view'=> 'Auth/ForgotPassword',
            'reset_password.view' => 'Auth/ResetPassword',
            'magic_link.view'     => 'Auth/MagicLink',
            'magic_link.sent'     => 'Auth/MagicLinkSent',
            'action.show'         => 'Auth/MfaChallenge',
            default               => 'Auth/' . ucfirst(str_replace(['.', '_'], '', $action)),
        };
    }

    public function modifyValidationFailed(array $errors, RequestInterface $request, array $options = []): ResponseInterface
    {
        session()->setFlashdata('errors', $errors);

        return redirect()->back()->withInput()->with('errors', $errors);
    }
}
