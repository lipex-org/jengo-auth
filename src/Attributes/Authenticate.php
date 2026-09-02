<?php

declare(strict_types=1);

namespace Jengo\Auth\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Authenticate
{
    public function __construct(
        public ?string $guard = null
    ) {}
}
