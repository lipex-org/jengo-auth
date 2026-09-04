<?php

declare(strict_types=1);

namespace Jengo\Auth\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Jengo\Auth\DTOs\AuthResponseData;
use Jengo\Auth\Support\ResponseHandler;
use Psr\Log\LoggerInterface;

abstract class BaseAuthController extends Controller
{
    protected ResponseHandler $responseHandler;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        if (function_exists('helper')) {
            helper('jengo');
        }

        $this->responseHandler = new ResponseHandler();
    }

    /**
     * Get the active ResponseHandler instance.
     */
    public function getResponseHandler(): ResponseHandler
    {
        return $this->responseHandler ??= new ResponseHandler();
    }

    /**
     * Set a custom ResponseHandler instance.
     */
    public function setResponseHandler(ResponseHandler $responseHandler): self
    {
        $this->responseHandler = $responseHandler;

        return $this;
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
     * Format and return the HTTP response via the ResponseHandler.
     */
    public function renderResponse(string $action, AuthResponseData $data, ?RequestInterface $request = null): ResponseInterface
    {
        $req = $request ?? $this->request;

        return $this->getResponseHandler()->render($action, $data, $req);
    }

    /**
     * Return a standardized 404 response for masked error handling or disabled features.
     */
    protected function notFoundResponse(string $action = 'auth.not_found'): ResponseInterface
    {
        return $this->getResponseHandler()->notFound($action, $this->request);
    }

    /**
     * Return a standardized 422 validation failure response.
     * @param string $action
     * @param array $errors
     */
    protected function validationFailedResponse(string $action, array $errors): ResponseInterface
    {
        return $this->getResponseHandler()->validationFailed($action, $errors, $this->request);
    }
}
