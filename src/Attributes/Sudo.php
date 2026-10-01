<?php

declare(strict_types=1);

namespace Jengo\Auth\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Sudo
{
    /**
     * @param int|string $lifetime Sudo session grace period (e.g. '2 hours', '15 minutes', or integer seconds 7200)
     * @param list<string> $factors Permitted verification factors ('passkey', 'totp', 'password', 'email_otp', 'recovery_code')
     * @param bool $forceFresh When true, requires immediate fresh re-verification ignoring active grace periods
     * @param string|null $redirectTo Custom redirect URL when Sudo verification is required
     */
    public function __construct(
        public int|string $lifetime = '2 hours',
        public array $factors = ['passkey', 'totp', 'password', 'email_otp', 'recovery_code'],
        public bool $forceFresh = false,
        public ?string $redirectTo = null
    ) {}
}
