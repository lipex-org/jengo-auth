<?php

declare(strict_types=1);

namespace Jengo\Auth\Contracts;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Jengo\Auth\Entities\User;

interface AuthActionInterface
{
    /**
     * Return the unique identifier for this action (e.g. 'email_2fa', 'totp_mfa', 'terms').
     */
    public function getActionName(): string;

    /**
     * Render or prepare the action view/challenge.
     */
    public function show(RequestInterface $request, User $user): ResponseInterface;

    /**
     * Process and verify the submitted action response.
     * Returns true if verified, false if invalid (which will trigger a 404 response to prevent enumeration).
     */
    public function verify(RequestInterface $request, User $user): bool;
}
