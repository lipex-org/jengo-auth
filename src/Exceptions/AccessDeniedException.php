<?php

declare(strict_types=1);

namespace Jengo\Auth\Exceptions;

use Exception;
use Vima\Core\Exceptions\AccessDeniedExceptionInterface;

class AccessDeniedException extends Exception implements AccessDeniedExceptionInterface
{
    public function __construct(
        string $message = 'Access Denied.',
        int $code = 403,
        ?\Throwable $previous = null,
        protected ?string $permission = null,
        protected mixed $user = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getPermission(): ?string
    {
        return $this->permission;
    }

    public function getUser(): mixed
    {
        return $this->user;
    }
}
