<?php

declare(strict_types=1);

namespace Jengo\Auth\Forms;

use Jengo\Base\Validation\FormHandler;

class RegisterFormHandler extends FormHandler
{
    protected array $rules = [
        'username'              => 'permit_empty|alpha_numeric_space|min_length[3]|max_length[30]',
        'email'                 => 'required|valid_email',
        'password'              => 'required|min_length[8]',
        'password_confirm'      => 'required|matches[password]',
    ];

    protected array $messages = [
        'email' => [
            'required'    => 'Email address is required.',
            'valid_email' => 'Please provide a valid email address.',
        ],
        'password_confirm' => [
            'matches' => 'Password confirmation does not match.',
        ],
    ];
}
