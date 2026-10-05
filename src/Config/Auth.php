<?php

declare(strict_types=1);

namespace Jengo\Auth\Config;

use CodeIgniter\Config\BaseConfig;
use Jengo\Auth\Modifiers\InertiaModifier;
use Jengo\Auth\Modifiers\JsonModifier;
use Jengo\Auth\Modifiers\StandardViewModifier;
use Jengo\Auth\Modifiers\UniversalModifier;
use Jengo\Auth\Notifications\DefaultEmailNotifier;
use Jengo\Auth\Authentication\Authenticators\{
    UniversalGuard,
    SessionGuard,
    TokenGuard,
};
use Jengo\Auth\TwoFactor\Drivers\EmailOtpDriver;
use Jengo\Auth\TwoFactor\Drivers\PasskeyDriver;
use Jengo\Auth\TwoFactor\Drivers\PasswordDriver;
use Jengo\Auth\TwoFactor\Drivers\RecoveryCodeDriver;
use Jengo\Auth\TwoFactor\Drivers\TotpDriver;

class Auth extends BaseConfig
{
    /**
     * Default authentication guard ('universal', 'session', 'token', or custom guard name).
     */
    public string $defaultGuard = "universal";

    /**
     * Available guard drivers mapping.
     * Developers can register their own custom Guard classes here or dynamically via auth()->extend().
     */
    public array $guards = [
        "universal" => UniversalGuard::class,
        "session" => SessionGuard::class,
        "token" => TokenGuard::class,
    ];

    /**
     * Branding settings for emails, default HTML views, and frontend/Inertia props.
     */
    public array $branding = [
        'name'        => 'Jengo',
        'logo'        => null,
        'companyName' => null,
    ];

    /**
     * Personal Access Token prefix formatting (e.g. 'jengo_pat_', 'acumen_pat_', 'acumen/pat/').
     */
    public string $tokenPrefix = 'jengo_pat_';

    /**
     * Feature toggles.
     */
    public bool $allowLogin = true;
    public bool $allowRegistration = true;
    public bool $allowMagicLink = true;
    public bool $allowPasswordReset = true;
    public bool $allowRemembering = true;
    public bool $allowTokens = true;

    /**
     * The response modifier class to format controller responses.
     * Built-in options:
     * - UniversalModifier::class (Auto-detects modifier based on request headers: Inertia, JSON, or Standard Views)
     * - StandardViewModifier::class (Traditional CI4 Views & HTML Flash redirects)
     * - JsonModifier::class (JSON REST APIs)
     * - InertiaModifier::class (Inertia.js SPA responses)
     */
    public string $responseModifier = UniversalModifier::class;

    /**
     * Modifiers used by UniversalModifier when dynamically routing responses.
     * Can be overridden with custom modifier implementations.
     */
    public string $inertiaModifier = InertiaModifier::class;
    public string $jsonModifier = JsonModifier::class;
    public string $standardModifier = StandardViewModifier::class;

    /**
     * The notification sender class for sending emails, SMS, or queued notifications.
     * Developers can replace this with their own queued dispatcher (e.g. RabbitMQ, Redis, Queue).
     */
    public string $notifier = DefaultEmailNotifier::class;

    /**
     * Post-Auth Actions Pipeline (e.g. Email 2FA, SMS MFA, Terms Agreement).
     * Supports a single action class or an array of sequential action classes per endpoint.
     * Set to null or empty array by default (MFA not enabled by default).
     *
     * Example:
     *   'login'    => [\Jengo\Auth\Actions\Email2FA::class, \App\Actions\TermsOfService::class],
     *   'register' => \Jengo\Auth\Actions\Email2FA::class,
     */
    public array $actions = [
        "login" => null,
        "register" => null,
    ];

    /**
     * Multi-Factor / Two-Factor Authentication configuration.
     */
    public array $twoFactor = [
        "enabled" => true,
        "drivers" => [
            "passkey"       => PasskeyDriver::class,
            "totp"          => TotpDriver::class,
            "email_otp"     => EmailOtpDriver::class,
            "recovery_code" => RecoveryCodeDriver::class,
            "password"      => PasswordDriver::class,
        ],
        "default" => "passkey",
    ];

    /**
     * Sudo Mode (Step-up privileged action re-verification) configuration.
     */
    public array $sudo = [
        "enabled"  => true,
        "lifetime" => 7200, // 2 hours
        "factors"  => ["passkey", "totp", "password", "email_otp", "recovery_code"],
    ];

    /**
     * View templates for standard HTML responses.
     */
    public array $views = [
        "login"               => "Jengo\Auth\Views\login",
        "register"            => 'Jengo\Auth\Views\register',
        "forgotPassword"      => 'Jengo\Auth\Views\forgot_password',
        "resetPassword"       => 'Jengo\Auth\Views\reset_password',
        "magicLink"           => "Jengo\Auth\Views\magic_link",
        "magicLinkSent"       => "Jengo\Auth\Views\magic_link_sent",
        "action_mfa"          => "Jengo\Auth\Views\mfa_challenge",
        "sudo"                => "Jengo\Auth\Views\sudo_challenge",
        'two_factor_settings' => 'Jengo\\Auth\\Views\\two_factor_settings',
        'tokens'              => 'Jengo\\Auth\\Views\\tokens_index',
    ];

    /**
     * View templates for emails and notifications.
     */
    public array $emailViews = [
        "magicLink" => "Jengo\Auth\Views\Email\magic_link",
        "passwordReset" => "Jengo\Auth\Views\Email\password_reset",
        "mfaCode" => "Jengo\Auth\Views\Email\mfa_code",
        "activation" => "Jengo\Auth\Views\Email\activation",
    ];

    /**
     * Session configuration.
     */
    public array $session = [
        "sessionKey" => "auth_user_id",
        "pendingUserKey" => "auth_pending_user_id",
        "pendingAction" => "auth_pending_action",
        "rememberCookie" => "remember_token",
        "rememberTTL" => 2592000, // 30 days
    ];

    /**
     * Throttling settings.
     */
    public array $throttling = [
        "maxAttempts" => 5,
        "decayMinutes" => 1,
    ];

    /**
     * Redirect destinations.
     * Use URL paths (e.g. '/', '/dashboard') for landing pages or route names (e.g. 'login') for authentication targets.
     */
    public array $redirects = [
        "login"          => "/",
        "register"       => "/",
        "logout"         => "login",
        "password_reset" => "login",
        "magic_link"     => "/",
        "sudo"           => "/",
        "action"         => "/",
        "denied"         => "login",
    ];
}
