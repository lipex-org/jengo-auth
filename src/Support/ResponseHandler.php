<?php

declare(strict_types=1);

namespace Jengo\Auth\Support;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Auth\Contracts\ResponseModifierInterface;
use Jengo\Auth\DTOs\AuthResponseData;
use Jengo\Auth\Modifiers\StandardViewModifier;
use Jengo\Base\Validation\FormFailedResponseHolder;

class ResponseHandler
{
    protected ?ResponseModifierInterface $modifier = null;

    public function __construct(?ResponseModifierInterface $modifier = null)
    {
        if ($modifier !== null) {
            $this->modifier = $modifier;
        }
    }

    /**
     * Get the active response modifier.
     */
    public function getModifier(): ResponseModifierInterface
    {
        if ($this->modifier !== null) {
            return $this->modifier;
        }

        $modifierClass = config('Auth')->responseModifier ?? StandardViewModifier::class;

        return $this->modifier = new $modifierClass();
    }

    /**
     * Set a custom response modifier.
     */
    public function setModifier(ResponseModifierInterface $modifier): self
    {
        $this->modifier = $modifier;

        return $this;
    }

    /**
     * Render an authentication response through the configured modifier.
     */
    public function render(string $action, AuthResponseData $data, ?RequestInterface $request = null): ResponseInterface
    {
        $req = $request ?? Services::request();

        return $this->getModifier()->modify($action, $data, $req);
    }

    /**
     * Render a standardized 404 response for masked failures or disabled features.
     */
    public function notFound(string $action = 'auth.not_found', ?RequestInterface $request = null): ResponseInterface
    {
        $data = new AuthResponseData(
            action: $action,
            status: 'error',
            statusCode: 404,
            message: 'Page Not Found'
        );

        return $this->render($action, $data, $request);
    }

    /**
     * Render a standardized 422 validation failure response.
     */
    public function validationFailed(string $action, array $errors, ?RequestInterface $request = null): ResponseInterface
    {
        $data = new AuthResponseData(
            action: $action,
            status: 'error',
            statusCode: 422,
            message: 'Validation failed.',
            errors: $errors
        );

        return $this->render($action, $data, $request);
    }

    /**
     * Listener for the 'jengo.form.failed' event to format validation errors through the active modifier.
     */
    public function handleFormFailed(FormFailedResponseHolder $holder, string $action = 'auth.validation_failed'): void
    {
        $response = $this->validationFailed($action, $holder->getErrors(), $holder->getRequest());
        $holder->setResponse($response);
    }
}
