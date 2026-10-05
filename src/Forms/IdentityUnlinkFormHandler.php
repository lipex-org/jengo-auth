<?php

declare(strict_types=1);

namespace Jengo\Auth\Forms;

use Jengo\Base\Validation\FormHandler;

class IdentityUnlinkFormHandler extends FormHandler
{
    protected array $rules = [
        'identity_id' => 'permit_empty|string',
        'provider'    => 'permit_empty|string',
    ];

    public function getIdentityId(): ?string
    {
        $data = $this->validated()->toArray();

        return !empty($data['identity_id']) ? (string) $data['identity_id'] : null;
    }

    public function getProvider(): ?string
    {
        $data = $this->validated()->toArray();

        return !empty($data['provider']) ? (string) $data['provider'] : null;
    }
}
