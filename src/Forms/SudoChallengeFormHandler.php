<?php

declare(strict_types=1);

namespace Jengo\Auth\Forms;

use Jengo\Base\Validation\FormHandler;

class SudoChallengeFormHandler extends FormHandler
{
    protected array $rules = [
        'factor' => 'required|string',
    ];

    protected array $messages = [
        'factor' => [
            'required' => 'A valid verification factor is required.',
        ],
    ];

    public function getFactor(): string
    {
        $data = $this->validated()->toArray();

        return (string) ($data['factor'] ?? '');
    }
}
