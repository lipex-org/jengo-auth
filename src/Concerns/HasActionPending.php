<?php

declare(strict_types=1);

namespace Jengo\Auth\Concerns;

use CodeIgniter\HTTP\RequestInterface;
use Jengo\Auth\Entities\User;

trait HasActionPending
{
    /**
     * Determine if this action is currently pending / required for the given user.
     * Returning false allows the action pipeline to automatically skip this action and advance to the next one.
     */
    abstract public function isPending(RequestInterface $request, User $user): bool;
}
