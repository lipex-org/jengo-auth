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
        if (!$data->isSuccess()) {
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
        $component = $this->resolveComponentForAction($action, $data->view, $data->data);
        if ($component !== null) {
            if (!class_exists(Inertia::class)) {
                throw new \RuntimeException(
                    'The jengo/inertia package is required to use InertiaModifier. Run: composer require jengo/inertia'
                );
            }

            $props = $data->toArray();
            unset($props['errors'], $props['flash']);

            return Inertia::render($component, $props);
        }

        // 3. Success Redirect
        $redirectUrl = $data->redirectTo ?? (function_exists('auth_redirect_url')
            ? auth_redirect_url('login', '/')
            : (config('Auth')->redirects['login'] ?? '/'));
        if ($data->message) {
            session()->setFlashdata('message', $data->message);
        }

        if (!empty($data->flash) && is_array($data->flash)) {
            foreach ($data->flash as $key => $value) {
                session()->setFlashdata($key, $value);
            }
        }

        $useInertiaLocation = $data->data['use_inertia_location'] ?? false;

        if ($useInertiaLocation) {
            return Inertia::location($redirectUrl);
        }

        return redirect()->to($redirectUrl);
    }

    protected function resolveComponentForAction(string $action, ?string $customView = null, array $data = []): ?string
    {
        if ($customView !== null) {
            return $customView;
        }

        $config = config('Auth');
        $views = $config->views ?? [];

        $configuredView = null;
        if ($action === 'action.show') {
            $actionName = $data['action'] ?? null;
            if ($actionName) {
                if (!empty($views['action_' . $actionName])) {
                    $configuredView = $views['action_' . $actionName];
                } elseif (!empty($views['action_mfa_' . $actionName])) {
                    $configuredView = $views['action_mfa_' . $actionName];
                }
            }
            if ($configuredView === null) {
                $configuredView = $views['action_mfa'] ?? null;
            }
        } else {
            $configuredView = match ($action) {
                'login.view' => $views['login'] ?? null,
                'register.view' => $views['register'] ?? null,
                'forgot_password.view' => $views['forgotPassword'] ?? null,
                'reset_password.view' => $views['resetPassword'] ?? null,
                'magic_link.view' => $views['magicLink'] ?? null,
                'magic_link.sent' => $views['magicLinkSent'] ?? null,
                'sudo.view', 'auth.sudo' => $views['sudo'] ?? null,
                'two_factor.view', 'two_factor.index' => $views['two_factor_settings'] ?? null,
                'tokens.list', 'tokens.index', 'tokens.view' => $views['tokens'] ?? null,
                default => null,
            };
        }

        if ($configuredView !== null) {
            // If configured with a custom Inertia component path (e.g. 'auth/custom-login' or 'pages/login')
            if (!str_starts_with($configuredView, 'Jengo\\Auth\\Views\\')) {
                return $configuredView;
            }

            // If configured with a default standard PHP view namespace path, convert cleanly to Inertia component format
            $base = basename(str_replace('\\', '/', $configuredView));
            return 'auth/' . strtolower($base);
        }

        return match ($action) {
            'login.view' => 'auth/login',
            'register.view' => 'auth/register',
            'forgot_password.view' => 'auth/forgot_password',
            'reset_password.view' => 'auth/reset_password',
            'magic_link.view' => 'auth/magic_link',
            'magic_link.sent' => 'auth/magic_link_sent',
            'action.show' => 'auth/mfa_challenge',
            'sudo.view', 'auth.sudo' => 'auth/sudo_challenge',
            'two_factor.view', 'two_factor.index' => 'auth/two_factor_settings',
            default => (str_ends_with($action, '.view') || str_ends_with($action, '.show') || str_ends_with($action, '.index'))
                ? 'auth/' . strtolower(str_replace(['.', '-'], '_', $action))
                : null,
        };
    }

    public function modifyValidationFailed(array $errors, RequestInterface $request, array $options = []): ResponseInterface
    {
        session()->setFlashdata('errors', $errors);

        return redirect()->back()->withInput()->with('errors', $errors);
    }
}
