<?php

declare(strict_types=1);

namespace Jengo\Auth\Forms;

use Jengo\Base\Validation\FormHandler;

class RegisterFormHandler extends FormHandler
{
    protected array $rules = [
        'username'         => 'permit_empty|alpha_dash|min_length[3]|max_length[30]',
        'email'            => 'required|valid_email',
        'password'         => 'required|min_length[8]',
        'password_confirm' => 'required|matches[password]',
    ];

    protected array $messages = [
        'email' => [
            'required'    => 'A valid email address is required.',
            'valid_email' => 'A valid email address is required.',
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

    public function getUsername(): ?string
    {
        $data = $this->validated()->toArray();
        $username = $data['username'] ?? null;

        return $username !== null && $username !== '' ? trim((string) $username) : null;
    }

    public function getEmail(): string
    {
        $data = $this->validated()->toArray();

        return strtolower(trim((string) $data['email']));
    }

    public function getPassword(): string
    {
        $data = $this->validated()->toArray();

        return (string) $data['password'];
    }
}
