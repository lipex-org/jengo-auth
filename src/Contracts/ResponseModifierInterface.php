<?php

declare(strict_types=1);

namespace Jengo\Auth\Contracts;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Jengo\Auth\DTOs\AuthResponseData;
use Jengo\Base\Contracts\ResponseModifierInterface as BaseResponseModifierInterface;

interface ResponseModifierInterface extends BaseResponseModifierInterface
{
    /**
     * Build and return an HTTP response based on the action identifier and DTO payload.
     *
     * @param string $action Unique identifier of the auth action/endpoint (e.g. 'login.view', 'login.success', etc.)
     * @param AuthResponseData $data The data transfer object containing payload, user, and status
     * @param RequestInterface $request The incoming HTTP request
     * @return ResponseInterface
     */
    public function modify(string $action, AuthResponseData $data, RequestInterface $request): ResponseInterface;
}
