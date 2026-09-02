<?php

declare(strict_types=1);

namespace Jengo\Auth\Exceptions;

use Vima\Core\Exceptions\AccessDeniedExceptionFactoryInterface;
use Vima\Core\Exceptions\AccessDeniedExceptionInterface;

class AccessDeniedExceptionFactory implements AccessDeniedExceptionFactoryInterface
{
    public function create(string $permission, mixed $user = null, mixed $userResolver = null): \Throwable&AccessDeniedExceptionInterface
    {
        $userId = is_object($user) ? ($user->username ?? $user->id ?? 'User') : 'User';
        $message = "Access Denied: [{$userId}] is not authorized to perform [{$permission}].";

        return new AccessDeniedException($message, 403, null, $permission, $user);
    }
}
