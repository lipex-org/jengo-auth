<?php

declare(strict_types=1);

namespace Jengo\Auth\Forms;

use Jengo\Base\Validation\FormHandler;

class LoginFormHandler extends FormHandler
{
    protected array $rules = [
        'email'      => 'permit_empty|string',
        'username'   => 'permit_empty|string',
        'identifier' => 'permit_empty|string',
        'password'   => 'required|string',
        'remember'   => 'permit_empty',
    ];

    protected array $messages = [
        'password' => [
            'required' => 'Password is required.',
        ],
    ];

    public function getIdentifier(): string
    {
        $data = $this->validated()->toArray();
        $ident = $data['email'] ?? $data['username'] ?? $data['identifier'] ?? '';

        return trim((string) $ident);
    }

    public function getPassword(): string
    {
        $data = $this->validated()->toArray();

        return (string) ($data['password'] ?? '');
    }

    public function isRemember(): bool
    {
        $data = $this->validated()->toArray();

        return (bool) ($data['remember'] ?? false);
    }
}
