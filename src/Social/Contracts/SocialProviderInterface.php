<?php

declare(strict_types=1);

namespace Jengo\Auth\Social\Contracts;

use Jengo\Auth\Social\DTOs\SocialUserDTO;

interface SocialProviderInterface
{
    /**
     * Get the unique provider identifier (e.g. 'google', 'github', 'microsoft').
     */
    public function getIdentifier(): string;

    /**
     * Generate the authorization redirect URL with state/PKCE.
     */
    public function getAuthUrl(array $options = []): string;

    /**
     * Handle the OAuth callback code exchange and retrieve normalized user profile.
     *
     * @param array $queryParams Usually $_GET / request query parameters containing 'code', 'state', etc.
     */
    public function handleCallback(array $queryParams): SocialUserDTO;
}
