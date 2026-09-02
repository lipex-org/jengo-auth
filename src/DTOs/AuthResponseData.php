<?php

declare(strict_types=1);

namespace Jengo\Auth\DTOs;

use Jengo\Auth\Entities\User;

class AuthResponseData
{
    public function __construct(
        public string $action,
        public string $status = 'success',
        public int $statusCode = 200,
        public ?string $message = null,
        public array $data = [],
        public array $errors = [],
        public ?string $redirectTo = null,
        public ?string $view = null,
        public ?User $user = null
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function toArray(): array
    {
        return [
            'action'      => $this->action,
            'status'      => $this->status,
            'statusCode'  => $this->statusCode,
            'message'     => $this->message,
            'data'        => $this->data,
            'errors'      => $this->errors,
            'redirectTo'  => $this->redirectTo,
            'user'        => $this->user ? [
                'id'       => $this->user->id,
                'username' => $this->user->username,
                'email'    => $this->user->getEmail(),
                'active'   => (bool) $this->user->active,
            ] : null,
        ];
    }
}
