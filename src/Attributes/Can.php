<?php

declare(strict_types=1);

namespace Jengo\Auth\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Can
{
    public function __construct(
        public string $permission,
        public ?string $resource = null,
        public ?string $schema = null
    ) {}
}
