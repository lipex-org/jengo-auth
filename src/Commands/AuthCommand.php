<?php

declare(strict_types=1);

namespace Jengo\Auth\Commands;

use Jengo\Base\Commands\Core\AbstractMasterCommand;

class AuthCommand extends AbstractMasterCommand
{
    protected $group        = 'Jengo';
    protected $name         = 'jengo:auth';
    protected $description  = 'Unified Authentication & Authorization manager for Jengo.';
    protected string $variantPath = 'Commands/Variants/Auth';
}
