<?php

declare(strict_types=1);

namespace Jengo\Auth\Notifications;

use Jengo\Auth\Contracts\NotificationSenderInterface;
use Jengo\Auth\Entities\User;

class ArrayNotifier implements NotificationSenderInterface
{
    public array $sent = [];

    public function sendMagicLink(User $user, string $token, string $url): bool
    {
        $this->sent[] = [
            'type'  => 'magicLink',
            'user'  => $user,
            'token' => $token,
            'url'   => $url,
        ];
        return true;
    }

    public function sendPasswordReset(User $user, string $token, string $url): bool
    {
        $this->sent[] = [
            'type'  => 'passwordReset',
            'user'  => $user,
            'token' => $token,
            'url'   => $url,
        ];
        return true;
    }

    public function sendMfaCode(User $user, string $code): bool
    {
        $this->sent[] = [
            'type' => 'mfaCode',
            'user' => $user,
            'code' => $code,
        ];
        return true;
    }

    public function sendActivation(User $user, string $token, string $url): bool
    {
        $this->sent[] = [
            'type'  => 'activation',
            'user'  => $user,
            'token' => $token,
            'url'   => $url,
        ];
        return true;
    }

    public function sendNotification(string $type, User $user, array $data = []): bool
    {
        $this->sent[] = [
            'type' => $type,
            'user' => $user,
            'data' => $data,
        ];
        return true;
    }

    public function hasSent(string $type, ?string $usernameOrEmail = null): bool
    {
        foreach ($this->sent as $item) {
            if ($item['type'] === $type) {
                if ($usernameOrEmail === null) {
                    return true;
                }
                /** @var User $u */
                $u = $item['user'];
                if ($u->username === $usernameOrEmail || $u->getEmail() === $usernameOrEmail) {
                    return true;
                }
            }
        }
        return false;
    }

    public function lastSent(): ?array
    {
        return empty($this->sent) ? null : end($this->sent);
    }

    public function reset(): void
    {
        $this->sent = [];
    }
}
