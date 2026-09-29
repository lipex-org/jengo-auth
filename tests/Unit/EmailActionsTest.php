<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use Config\App;
use Config\Services;
use Jengo\Auth\Actions\Email2FA;
use Jengo\Auth\Actions\EmailActivator;
use Jengo\Auth\Entities\User;
use Tests\TestCase;

class EmailActionsTest extends TestCase
{
    protected function createUniqueUser(): User
    {
        $uid = uniqid();
        $user = new User([
            'username' => 'user_' . $uid,
            'email'    => 'action_' . $uid . '@example.com',
            'active'   => 0,
            'status'   => 'pending',
        ]);
        $id = auth()->getUserModel()->insert($user);
        $user->id = (int) $id;
        return $user;
    }

    public function testEmail2FAActionShowAndVerifySuccess(): void
    {
        $user = $this->createUniqueUser();
        $action = new Email2FA();
        $this->assertSame('email_2fa', $action->getActionName());

        $request = new IncomingRequest(new App(), new URI('http://example.com/auth/action/show'), null, new UserAgent());
        $response = $action->show($request, $user);
        $this->assertContains($response->getStatusCode(), [200, 302]);

        $session = Services::session();
        $code = (string) $session->get('mfa_code');
        $this->assertNotEmpty($code);
        $this->assertSame(6, strlen($code));

        // Verify with matching code
        $verifyRequest = (new IncomingRequest(new App(), new URI('http://example.com/auth/action/verify'), null, new UserAgent()))
            ->withMethod('POST')
            ->setBody(json_encode(['code' => $code]));

        $verified = $action->verify($verifyRequest, $user);
        $this->assertTrue($verified);

        // Code should now be cleared
        $this->assertNull($session->get('mfa_code'));
    }

    public function testEmail2FAActionVerifyFailureOnInvalidOrExpiredCode(): void
    {
        $user = $this->createUniqueUser();
        $action = new Email2FA();
        $session = Services::session();
        $session->set('mfa_code', '123456');
        $session->set('mfa_expires', time() + 300);

        // Wrong code
        $wrongRequest = (new IncomingRequest(new App(), new URI('http://example.com/auth/action/verify'), null, new UserAgent()))
            ->withMethod('POST')
            ->setBody(json_encode(['code' => '999999']));

        $this->assertFalse($action->verify($wrongRequest, $user));

        // Expired code
        $session->set('mfa_expires', time() - 10);
        $this->assertFalse($action->verify($wrongRequest, $user));
    }

    public function testEmailActivatorShowAndVerifyActivatesUser(): void
    {
        $user = $this->createUniqueUser();
        $action = new EmailActivator();
        $this->assertSame('email_activator', $action->getActionName());

        $request = new IncomingRequest(new App(), new URI('http://example.com/auth/action/show'), null, new UserAgent());
        $response = $action->show($request, $user);
        $this->assertContains($response->getStatusCode(), [200, 302]);

        $session = Services::session();
        $code = (string) $session->get('activation_code');
        $this->assertNotEmpty($code);

        // Verify with matching code
        $verifyRequest = (new IncomingRequest(new App(), new URI('http://example.com/auth/action/verify'), null, new UserAgent()))
            ->withMethod('POST')
            ->setBody(json_encode(['code' => $code]));

        $this->assertTrue($action->verify($verifyRequest, $user));

        // Reload user and verify active status
        $refreshed = auth()->getUserModel()->find($user->id);
        $this->assertSame(1, (int) $refreshed->active);
        $this->assertSame('active', $refreshed->status);
    }
}
