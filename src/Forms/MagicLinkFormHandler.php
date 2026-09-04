<?php

declare(strict_types=1);

namespace Jengo\Auth\Forms;

use Jengo\Base\Validation\FormHandler;

class MagicLinkFormHandler extends FormHandler
{
    protected array $rules = [
        'email' => 'required|valid_email',
    ];

    protected array $messages = [
        'email' => [
            'required'    => 'Valid email is required.',
            'valid_email' => 'Valid email is required.',
        ],
    ];

    public function getEmail(): string
    {
        $data = $this->validated()->toArray();

        return strtolower(trim((string) $data['email']));
    }
}
