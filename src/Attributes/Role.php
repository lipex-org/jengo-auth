<?php

declare(strict_types=1);

namespace Jengo\Auth\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Role
{
    public array $roles;

    public function __construct(string ...$roles)
    {
        $this->roles = $roles;
    }
}
