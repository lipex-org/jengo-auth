<?php

declare(strict_types=1);

namespace Jengo\Auth\Modifiers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Auth\Contracts\ResponseModifierInterface;
use Jengo\Auth\DTOs\AuthResponseData;

class JsonModifier implements ResponseModifierInterface
{
    public function modify(string $action, AuthResponseData $data, RequestInterface $request): ResponseInterface
    {
        $payload = [
            'action'  => $action,
            'status'  => $data->status,
            'message' => $data->message,
        ];

        if (! empty($data->errors)) {
            $payload['errors'] = $data->errors;
        }

        if (! empty($data->data)) {
            $payload['data'] = $data->data;
        }

        if ($data->user) {
            $payload['user'] = [
                'id'       => $data->user->id,
                'username' => $data->user->username,
                'email'    => $data->user->getEmail(),
                'active'   => (bool) $data->user->active,
            ];
        }

        if ($data->redirectTo) {
            $payload['redirect_to'] = $data->redirectTo;
        }

        return Services::response()
            ->setStatusCode($data->statusCode)
            ->setJSON($payload);
    }

    public function modifyValidationFailed(array $errors, RequestInterface $request, array $options = []): ResponseInterface
    {
        return Services::response()
            ->setStatusCode(422)
            ->setJSON([
                'action'  => $options['action'] ?? 'validation.failed',
                'status'  => 'error',
                'message' => 'The given data was invalid.',
                'errors'  => $errors,
            ]);
    }
}
