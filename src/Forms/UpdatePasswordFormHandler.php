<?php

declare(strict_types=1);

namespace Jengo\Auth\Forms;

use Jengo\Base\Validation\FormHandler;

class UpdatePasswordFormHandler extends FormHandler
{
    protected array $rules = [
        'current_password' => 'required',
        'password'         => 'required|min_length[8]',
        'password_confirm' => 'required|matches[password]',
    ];
}
