<?php

declare(strict_types=1);

namespace Jengo\Auth\Forms;

use Jengo\Base\Validation\FormHandler;

class ResetPasswordFormHandler extends FormHandler
{
    protected array $rules = [
        'token'            => 'required',
        'email'            => 'required|valid_email',
        'password'         => 'required|min_length[8]',
        'password_confirm' => 'required|matches[password]',
    ];
}
