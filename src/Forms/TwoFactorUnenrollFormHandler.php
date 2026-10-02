<?php

declare(strict_types=1);

namespace Jengo\Auth\Forms;

use Jengo\Base\Validation\FormHandler;

class TwoFactorUnenrollFormHandler extends FormHandler
{
    protected array $rules = [
        'factor'        => 'required|string',
        'credential_id' => 'permit_empty|string',
    ];

    protected array $messages = [
        'factor' => [
            'required' => 'A factor type is required to unenroll.',
        ],
    ];

    public function getFactor(): string
    {
        $data = $this->validated()->toArray();

        return (string) ($data['factor'] ?? '');
    }

    public function getCredentialId(): ?string
    {
        $data = $this->validated()->toArray();

        return !empty($data['credential_id']) ? (string) $data['credential_id'] : null;
    }
}
