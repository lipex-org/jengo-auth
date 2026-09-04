<?php

declare(strict_types=1);

namespace Jengo\Auth\Forms;

use Jengo\Base\Validation\FormHandler;

class ResetPasswordFormHandler extends FormHandler
{
    protected array $rules = [
        'token'            => 'required|string',
        'password'         => 'required|min_length[8]',
        'password_confirm' => 'required|matches[password]',
    ];

    protected array $messages = [
        'token' => [
            'required' => 'Reset token is required.',
        ],
        'password' => [
            'required'   => 'Password must be at least 8 characters long.',
            'min_length' => 'Password must be at least 8 characters long.',
        ],
        'password_confirm' => [
            'required' => 'Passwords do not match.',
            'matches'  => 'Passwords do not match.',
        ],
    ];

    public function getToken(): string
    {
        $data = $this->validated()->toArray();

        return (string) $data['token'];
    }

    public function getPassword(): string
    {
        $data = $this->validated()->toArray();

        return (string) $data['password'];
    }
}
