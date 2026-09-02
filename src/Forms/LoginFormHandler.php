<?php

declare(strict_types=1);

namespace Jengo\Auth\Forms;

use Jengo\Base\Validation\FormHandler;

class LoginFormHandler extends FormHandler
{
    protected array $rules = [
        'email'    => 'permit_empty|valid_email',
        'username' => 'permit_empty|alpha_numeric_space|min_length[3]',
        'password' => 'required|min_length[6]',
        'remember' => 'permit_empty',
    ];

    protected array $messages = [
        'password' => [
            'required'   => 'Password is required.',
            'min_length' => 'Password must be at least 6 characters.',
        ],
    ];
}
