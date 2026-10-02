<?php

declare(strict_types=1);

namespace Jengo\Auth\Forms;

use Jengo\Base\Validation\FormHandler;

class TwoFactorEnrollConfirmFormHandler extends FormHandler
{
    protected array $rules = [
        'factor'   => 'required|string',
        'proof'    => 'permit_empty',
        'code'     => 'permit_empty|string',
        'metadata' => 'permit_empty',
    ];

    protected array $messages = [
        'factor' => [
            'required' => 'A factor type is required.',
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

        return $data['proof'] ?? $data['code'] ?? null;
    }

    public function getMetadata(): array
    {
        $data = $this->validated()->toArray();

        return is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
    }
}
