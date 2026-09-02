<?php

declare(strict_types=1);

namespace Tests\Unit;

use Config\Services;
use Jengo\Auth\Actions\Email2FA;
use Jengo\Auth\Controllers\ForgotPasswordController;
use Jengo\Auth\Controllers\MagicLinkController;
use Jengo\Auth\Entities\User;
use Jengo\Auth\Entities\UserIdentity;
use Jengo\Auth\Notifications\ArrayNotifier;
use Jengo\Auth\Notifications\DefaultEmailNotifier;
use Tests\TestCase;

class NotificationSenderTest extends TestCase
{
    public function testDefaultEmailNotifierRendersTemplates(): void
    {
        $notifier = new DefaultEmailNotifier();
        $user = new User(['id' => 1, 'username' => 'dave']);

        // Attach identity with email and secret
        $identity = new UserIdentity([
            'user_id' => 1,
            'type'    => 'email_password',
            'name'    => 'dave@example.com',
            'secret'  => 'dummy_hash',
        ]);
        auth()->getUserIdentityModel()->insert($identity);

        $sentMagic = $notifier->sendMagicLink($user, 'test-token', 'https://example.com/magic/test');
        $sentReset = $notifier->sendPasswordReset($user, 'reset-token', 'https://example.com/reset/test');
        $sentMfa   = $notifier->sendMfaCode($user, '123456');
        $sentAct   = $notifier->sendActivation($user, 'act-token', 'https://example.com/act/test');

        // Email service in test environment handles sending
        $this->assertIsBool($sentMagic);
        $this->assertIsBool($sentReset);
        $this->assertIsBool($sentMfa);
        $this->assertIsBool($sentAct);
    }

    public function testCustomNotificationSenderPluggabilityInControllers(): void
    {
        // 1. Create a custom notifier (e.g. Queue / In-Memory / Webhook)
        $arrayNotifier = new ArrayNotifier();
        auth()->setNotifier($arrayNotifier);

        // 2. Create User in DB
        $user = new User(['username' => 'eve', 'active' => 1, 'status' => 'active']);
        $userId = auth()->getUserModel()->insert($user);
        $user->id = (int) $userId;

        $identity = new UserIdentity([
            'user_id' => $user->id,
            'type'    => 'email_password',
            'name'    => 'eve@example.com',
            'secret'  => 'dummy_hash',
        ]);
        auth()->getUserIdentityModel()->insert($identity);

        // 3. Trigger ForgotPasswordController
        $request = Services::request();
        $request->setBody(json_encode(['email' => 'eve@example.com']));

        $forgotController = new ForgotPasswordController();
        $forgotController->initController($request, Services::response(), Services::logger());
        $forgotController->sendResetLink();

        // Verify custom notifier intercepted password reset email
        $this->assertTrue($arrayNotifier->hasSent('passwordReset', 'eve'));
        $lastReset = $arrayNotifier->lastSent();
        $this->assertSame('passwordReset', $lastReset['type']);
        $this->assertNotEmpty($lastReset['token']);
        $this->assertStringContainsString('reset-password', $lastReset['url']);

        // 4. Trigger MagicLinkController
        $magicController = new MagicLinkController();
        $magicController->initController($request, Services::response(), Services::logger());
        $magicController->sendLink();

        // Verify custom notifier intercepted magic link email
        $this->assertTrue($arrayNotifier->hasSent('magicLink', 'eve'));
        $lastMagic = $arrayNotifier->lastSent();
        $this->assertSame('magicLink', $lastMagic['type']);
        $this->assertNotEmpty($lastMagic['token']);
        $this->assertStringContainsString('magic-link/verify', $lastMagic['url']);

        // 5. Trigger Email2FA MFA Action
        $mfaAction = new Email2FA();
        $mfaAction->show($request, $user);

        // Verify custom notifier intercepted MFA 6-digit code
        $this->assertTrue($arrayNotifier->hasSent('mfaCode', 'eve'));
        $lastMfa = $arrayNotifier->lastSent();
        $this->assertSame('mfaCode', $lastMfa['type']);
        $this->assertSame(6, strlen((string) $lastMfa['code']));
    }
}
