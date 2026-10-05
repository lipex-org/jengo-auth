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

        if ($action === 'action.show') {
            $actionName = $data['action'] ?? null;
            if ($actionName) {
                if (!empty($views['action_' . $actionName])) {
                    return $views['action_' . $actionName];
                }
                if (!empty($views['action_mfa_' . $actionName])) {
                    return $views['action_mfa_' . $actionName];
                }
            }
            return $views['action_mfa'] ?? null;
        }

        return match ($action) {
            'login.view'                                 => $views['login'] ?? null,
            'register.view'                              => $views['register'] ?? null,
            'forgot_password.view'                       => $views['forgotPassword'] ?? null,
            'reset_password.view'                        => $views['resetPassword'] ?? null,
            'magic_link.view'                            => $views['magicLink'] ?? null,
            'sudo.view', 'auth.sudo'                     => $views['sudo'] ?? null,
            'two_factor.view', 'two_factor.index'        => $views['two_factor_settings'] ?? null,
            'tokens.list', 'tokens.index', 'tokens.view' => $views['tokens'] ?? null,
            'set_password.view'                          => $views['set_password'] ?? null,
            'identities.view', 'identities.index'        => $views['identities'] ?? null,
            default                                      => $views[$action] ?? null,
        };
    }
    public function modifyValidationFailed(array $errors, RequestInterface $request, array $options = []): ResponseInterface
    {
        session()->setFlashdata('errors', $errors);

        return redirect()->back()->withInput()->with('errors', $errors);
    }
}
