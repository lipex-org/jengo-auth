<?php

declare(strict_types=1);

namespace Jengo\Auth\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Jengo\Auth\Attributes\Authenticate;
use Jengo\Auth\DTOs\AuthResponseData;

#[Authenticate]
class TokenController extends BaseAuthController
{
    public function index(): ResponseInterface
    {
        if ($disabled = $this->ensureFeatureEnabled('allowTokens', 'tokens')) {
            return $disabled;
        }

        $user = auth()->user();
        $tokens = auth()->getUserTokenModel()->where('user_id', $user->id)->findAll();

        $data = new AuthResponseData(
            action: 'tokens.list',
            status: 'success',
            statusCode: 200,
            data: ['tokens' => $tokens],
            user: $user
        );

        return $this->renderResponse('tokens.list', $data);
    }

    public function create(): ResponseInterface
    {
        if ($disabled = $this->ensureFeatureEnabled('allowTokens', 'tokens')) {
            return $disabled;
        }

        $user = auth()->user();
        $payload = $this->extractPayload();

        $name = $payload['name'] ?? 'Personal Access Token';
        $abilities = $payload['abilities'] ?? ['*'];

        $result = auth()->createTokenFor($user, (string) $name, (array) $abilities);

        $data = new AuthResponseData(
            action: 'tokens.created',
            status: 'success',
            statusCode: 201,
            message: 'Personal access token created successfully.',
            data: [
                'token'       => $result->plainTextToken,
                'accessToken' => $result->accessToken,
            ],
            user: $user
        );

        return $this->renderResponse('tokens.created', $data);
    }

    public function revoke(int|string $tokenId): ResponseInterface
    {
        if ($disabled = $this->ensureFeatureEnabled('allowTokens', 'tokens')) {
            return $disabled;
        }

        $user = auth()->user();
        auth()->getUserTokenModel()->where(['id' => (int) $tokenId, 'user_id' => $user->id])->delete();

        $data = new AuthResponseData(
            action: 'tokens.revoked',
            status: 'success',
            statusCode: 200,
            message: 'Token revoked successfully.'
        );

        return $this->renderResponse('tokens.revoked', $data);
    }
}
