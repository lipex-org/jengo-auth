<?php

declare(strict_types=1);

namespace Jengo\Auth\Entities;

use Jengo\Base\Entities\BaseEntity;

class UserIdentity extends BaseEntity
{
    protected array $obfuscatedFields = ['id', 'user_id'];

    protected array $hidden = ['secret', 'secret2'];

    protected $casts = [
        'id'          => 'integer',
        'user_id'     => 'integer',
        'force_reset' => 'boolean',
        'expires'     => 'datetime',
        'last_used_at'=> 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];
}
