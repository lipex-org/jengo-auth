<?php

declare(strict_types=1);

namespace Jengo\Auth\Contracts;

use Jengo\Auth\Entities\User;

interface NotificationSenderInterface
{
    /**
     * Send a magic link authentication email.
     *
     * @param User $user The recipient user
     * @param string $token The raw one-time security token
     * @param string $url The complete verification URL with query or segment token
     */
    public function sendMagicLink(User $user, string $token, string $url): bool;

    /**
     * Send a password reset link email.
     *
     * @param User $user The recipient user
     * @param string $token The raw password reset token
     * @param string $url The complete reset password URL
     */
    public function sendPasswordReset(User $user, string $token, string $url): bool;

    /**
     * Send a two-factor / multi-factor authentication security code.
     *
     * @param User $user The recipient user
     * @param string $code The 6-digit verification code
     */
    public function sendMfaCode(User $user, string $code): bool;

    /**
     * Send an account activation / verification email.
     *
     * @param User $user The newly registered recipient user
     * @param string $token The activation token
     * @param string $url The complete activation URL
     */
    public function sendActivation(User $user, string $token, string $url): bool;

    /**
     * Send a custom or arbitrary auth-related notification.
     *
     * @param string $type The notification type identifier
     * @param User $user The recipient user
     * @param array $data Contextual payload for the notification
     */
    public function sendNotification(string $type, User $user, array $data = []): bool;
}
