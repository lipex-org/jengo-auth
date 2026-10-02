<?php

declare(strict_types=1);

namespace Jengo\Auth\Forms;

use Jengo\Base\Validation\FormHandler;

class TwoFactorEnrollStartFormHandler extends FormHandler
{
    protected array $rules = [
        'factor'  => 'required|string',
        'options' => 'permit_empty',
    ];

    protected array $messages = [
        'factor' => [
            'required' => 'A factor type is required to begin enrollment.',
        ],
    ];

    public function getFactor(): string
    {
        $data = $this->validated()->toArray();

        return (string) ($data['factor'] ?? '');
    }

    public function getOptions(): array
    {
        $data = $this->validated()->toArray();

        return is_array($data['options'] ?? null) ? $data['options'] : [];
    }
}
