<?php

declare(strict_types=1);

namespace Jengo\Auth\Forms;

use Jengo\Base\Validation\FormHandler;

class ForgotPasswordFormHandler extends FormHandler
{
    protected array $rules = [
        'email' => 'required|valid_email',
    ];
}
