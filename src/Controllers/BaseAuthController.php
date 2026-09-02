<?php

declare(strict_types=1);

namespace Jengo\Auth\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Auth\Contracts\ResponseModifierInterface;
use Jengo\Auth\DTOs\AuthResponseData;
use Jengo\Auth\Modifiers\StandardViewModifier;
use Psr\Log\LoggerInterface;

abstract class BaseAuthController extends Controller
{
    protected ResponseModifierInterface $responseModifier;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $modifierClass = config('Auth')->responseModifier ?? StandardViewModifier::class;
        $this->responseModifier = new $modifierClass();
    }

    /**
     * Check if a feature is enabled in Auth config.
     */
    protected function isFeatureEnabled(string $feature): bool
    {
        $config = config('Auth');
        return (bool) ($config->$feature ?? true);
    }

    /**
     * Return a 404 response if the feature is disabled in Auth config, or null if enabled.
     */
    protected function ensureFeatureEnabled(string $feature, string $actionName): ?ResponseInterface
    {
        if (! $this->isFeatureEnabled($feature)) {
            return $this->notFoundResponse($actionName . '.disabled');
        }

        return null;
    }

    /**
     * Extract request input payload safely supporting both JSON payloads and standard POST form bodies.
     */
    protected function extractPayload(?RequestInterface $request = null): array
    {
        $req = $request ?? $this->request;

        $rawBody = (string) $req->getBody();
        if ($rawBody !== '') {
            $trimmed = trim($rawBody);
            if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded) && ! empty($decoded)) {
                    return $decoded;
                }
            }
        }

        $post = $req->getPost();
        if (is_array($post) && ! empty($post)) {
            return $post;
        }

        if (! empty($_POST)) {
            return $_POST;
        }

        return $req->getGet() ?? [];
    }

    /**
     * Format and return the HTTP response via the configured ResponseModifier.
     */
    public function renderResponse(string $action, AuthResponseData $data, ?RequestInterface $request = null): ResponseInterface
    {
        $req = $request ?? $this->request;
        return $this->responseModifier->modify($action, $data, $req);
    }

    /**
     * Return a standardized 404 response for masked error handling or disabled features.
     */
    protected function notFoundResponse(string $action = 'auth.not_found'): ResponseInterface
    {
        $data = new AuthResponseData(
            action: $action,
            status: 'error',
            statusCode: 404,
            message: 'Page Not Found'
        );

        return $this->renderResponse($action, $data);
    }
}
