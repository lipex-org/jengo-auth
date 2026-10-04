<?php

declare(strict_types=1);

namespace Jengo\Auth\Concerns;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Jengo\Auth\Entities\User;

trait HasActionChallenge
{
    /**
     * Perform or re-issue a challenge for this action (e.g. resend 2FA code, refresh token).
     */
    abstract public function challenge(RequestInterface $request, User $user): ResponseInterface;
}
