<?php

declare(strict_types=1);

namespace Jengo\Auth\Config;

use CodeIgniter\Config\BaseConfig;
use Jengo\Auth\Modifiers\StandardViewModifier;
use Jengo\Auth\Notifications\DefaultEmailNotifier;

class Auth extends BaseConfig
{
    /**
     * Default authentication guard ('universal', 'session', 'token').
     */
    public string $defaultGuard = 'universal';

    /**
     * Feature toggles.
     */
    public bool $allowLogin         = true;
    public bool $allowRegistration  = true;
    public bool $allowMagicLink     = true;
    public bool $allowPasswordReset = true;
    public bool $allowRemembering   = true;
    public bool $allowTokens        = true;

    /**
     * The response modifier class to format controller responses.
     * Built-in options:
     * - StandardViewModifier::class (Traditional CI4 Views & HTML Flash redirects)
     * - JsonModifier::class (JSON REST APIs)
     * - InertiaModifier::class (Inertia.js SPA responses)
     */
    public string $responseModifier = StandardViewModifier::class;

    /**
     * The notification sender class for sending emails, SMS, or queued notifications.
     * Developers can replace this with their own queued dispatcher (e.g. RabbitMQ, Redis, Queue).
     */
    public string $notifier = DefaultEmailNotifier::class;

    /**
     * Post-Auth Actions Pipeline (e.g. Email 2FA, SMS MFA, Terms Agreement).
     * Set to null by default (MFA not enabled by default).
     *
     * Example:
     *   'login'    => \Jengo\Auth\Actions\Email2FA::class,
     *   'register' => \Jengo\Auth\Actions\EmailActivator::class,
     */
    public array $actions = [
        'login'    => null,
        'register' => null,
    ];

    /**
     * View templates for standard HTML responses.
     */
    public array $views = [
        'login'          => 'Jengo\Auth\Views\login',
        'register'       => 'Jengo\Auth\Views\register',
        'forgotPassword' => 'Jengo\Auth\Views\forgot_password',
        'resetPassword'  => 'Jengo\Auth\Views\reset_password',
        'magicLink'      => 'Jengo\Auth\Views\magic_link',
        'magicLinkSent'  => 'Jengo\Auth\Views\magic_link_sent',
        'action_mfa'     => 'Jengo\Auth\Views\mfa_challenge',
    ];

    /**
     * View templates for emails and notifications.
     */
    public array $emailViews = [
        'magicLink'     => 'Jengo\Auth\Views\Email\magic_link',
        'passwordReset' => 'Jengo\Auth\Views\Email\password_reset',
        'mfaCode'       => 'Jengo\Auth\Views\Email\mfa_code',
        'activation'    => 'Jengo\Auth\Views\Email\activation',
    ];

    /**
     * Email sender configuration.
     */
    public array $emailConfig = [
        'fromEmail' => 'noreply@example.com',
        'fromName'  => 'Jengo Auth',
    ];

    /**
     * Session configuration.
     */
    public array $session = [
        'sessionKey'     => 'auth_user_id',
        'pendingUserKey' => 'auth_pending_user_id',
        'pendingAction'  => 'auth_pending_action',
        'rememberCookie' => 'remember_token',
        'rememberTTL'    => 2592000, // 30 days
    ];

    /**
     * Throttling settings.
     */
    public array $throttling = [
        'maxAttempts'  => 5,
        'decayMinutes' => 1,
    ];

    /**
     * Audit logging toggle.
     */
    public bool $auditEnabled = true;

    /**
     * Inertia shared props key.
     */
    public string $inertiaAuthKey = 'auth';

    /**
     * Redirect routes.
     */
    public array $redirects = [
        'login'  => '/login',
        'home'   => '/dashboard',
        'logout' => '/login',
        'denied' => '/403',
    ];
}
