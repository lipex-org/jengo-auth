<?php

declare(strict_types=1);

namespace Jengo\Auth\Forms;

use Jengo\Base\Validation\FormHandler;

class SudoVerifyFormHandler extends FormHandler
{
    protected array $rules = [
        'factor'   => 'required|string',
        'proof'    => 'required',
        'lifetime' => 'permit_empty|is_natural_no_zero',
    ];

    protected array $messages = [
        'factor' => [
            'required' => 'A verification factor is required.',
        ],
        'proof' => [
            'required' => 'Verification proof is required.',
        ],
    ];

    public function getFactor(): string
    {
        $data = $this->validated()->toArray();

        return (string) ($data['factor'] ?? '');
    }

    public function getProof(): mixed
    {
        $data = $this->validated()->toArray();

        return $data['proof'] ?? null;
    }

    public function getLifetime(): int
    {
        $data = $this->validated()->toArray();

        return !empty($data['lifetime']) ? (int) $data['lifetime'] : (int) (config('Auth')->sudo['lifetime'] ?? 7200);
    }
}
