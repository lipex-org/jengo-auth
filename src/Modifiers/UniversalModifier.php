<?php

declare(strict_types=1);

namespace Jengo\Auth\Modifiers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Jengo\Auth\Contracts\ResponseModifierInterface;
use Jengo\Auth\DTOs\AuthResponseData;

class UniversalModifier implements ResponseModifierInterface
{
    /**
     * @param ResponseModifierInterface|class-string<ResponseModifierInterface>|null $inertiaModifier
     * @param ResponseModifierInterface|class-string<ResponseModifierInterface>|null $jsonModifier
     * @param ResponseModifierInterface|class-string<ResponseModifierInterface>|null $standardModifier
     */
    public function __construct(
        protected ResponseModifierInterface|string|null $inertiaModifier = null,
        protected ResponseModifierInterface|string|null $jsonModifier = null,
        protected ResponseModifierInterface|string|null $standardModifier = null,
    ) {
    }

    public function modify(string $action, AuthResponseData $data, RequestInterface $request): ResponseInterface
    {
        $modifier = $this->resolveModifier($request);

        return $modifier->modify($action, $data, $request);
    }

    public function modifyValidationFailed(array $errors, RequestInterface $request, array $options = []): ResponseInterface
    {
        $modifier = $this->resolveModifier($request);

        return $modifier->modifyValidationFailed($errors, $request, $options);
    }

    /**
     * Resolve the target modifier based on incoming request headers / state.
     */
    public function resolveModifier(RequestInterface $request): ResponseModifierInterface
    {
        // 1. Inertia request detection (X-Inertia header)
        if ($this->isInertiaRequest($request)) {
            return $this->getInertiaModifier();
        }

        // 2. JSON / API / AJAX request detection
        if ($this->isJsonRequest($request)) {
            return $this->getJsonModifier();
        }

        // 3. Fallback to standard view modifier (traditional HTML/form)
        return $this->getStandardModifier();
    }

    protected function isInertiaRequest(RequestInterface $request): bool
    {
        if (!$request->hasHeader('X-Inertia')) {
            return false;
        }

        $line = trim((string) $request->getHeaderLine('X-Inertia'));

        return $line !== '' && strtolower($line) !== 'false';
    }

    protected function isJsonRequest(RequestInterface $request): bool
    {
        if (method_exists($request, 'isAJAX') && $request->isAJAX()) {
            return true;
        }

        $accept = (string) $request->getHeaderLine('Accept');
        $contentType = (string) $request->getHeaderLine('Content-Type');

        return str_contains($accept, 'application/json') || str_contains($contentType, 'application/json');
    }

    public function getInertiaModifier(): ResponseModifierInterface
    {
        if ($this->inertiaModifier instanceof ResponseModifierInterface) {
            return $this->inertiaModifier;
        }

        $configuredClass = $this->inertiaModifier
            ?? config('Auth')->inertiaModifier
            ?? InertiaModifier::class;

        return $this->inertiaModifier = new $configuredClass();
    }

    public function getJsonModifier(): ResponseModifierInterface
    {
        if ($this->jsonModifier instanceof ResponseModifierInterface) {
            return $this->jsonModifier;
        }

        $configuredClass = $this->jsonModifier
            ?? config('Auth')->jsonModifier
            ?? JsonModifier::class;

        return $this->jsonModifier = new $configuredClass();
    }

    public function getStandardModifier(): ResponseModifierInterface
    {
        if ($this->standardModifier instanceof ResponseModifierInterface) {
            return $this->standardModifier;
        }

        $configuredClass = $this->standardModifier
            ?? config('Auth')->standardModifier
            ?? StandardViewModifier::class;

        return $this->standardModifier = new $configuredClass();
    }

    public function setInertiaModifier(ResponseModifierInterface|string $modifier): self
    {
        $this->inertiaModifier = $modifier;

        return $this;
    }

    public function setJsonModifier(ResponseModifierInterface|string $modifier): self
    {
        $this->jsonModifier = $modifier;

        return $this;
    }

    public function setStandardModifier(ResponseModifierInterface|string $modifier): self
    {
        $this->standardModifier = $modifier;

        return $this;
    }
}
