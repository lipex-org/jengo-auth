<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Auth\Entities\User;
use Jengo\Auth\TwoFactor\Engines\WebAuthnEngine;

final class WebAuthnEngineTest extends CIUnitTestCase
{
    public function testBase64UrlEncodeAndDecode(): void
    {
        $raw = 'Binary_Data_With_Special_Chars+123/=';
        $encoded = WebAuthnEngine::base64UrlEncode($raw);
        $this->assertFalse(str_contains($encoded, '+'));
        $this->assertFalse(str_contains($encoded, '/'));
        $this->assertFalse(str_contains($encoded, '='));

        $decoded = WebAuthnEngine::base64UrlDecode($encoded);
        $this->assertSame($raw, $decoded);
    }

    public function testGenerateCreationOptions(): void
    {
        $user = new User(['id' => 42, 'email' => 'passkey@example.com', 'username' => 'passkey_user']);
        $options = WebAuthnEngine::generateCreationOptions($user, 'Test App', 'localhost');

        $this->assertArrayHasKey('challenge', $options);
        $this->assertSame('Test App', $options['rp']['name']);
        $this->assertSame('localhost', $options['rp']['id']);
        $this->assertSame('passkey@example.com', $options['user']['name']);
        $this->assertNotEmpty($options['pubKeyCredParams']);
    }

    public function testGenerateRequestOptions(): void
    {
        $allowCreds = [
            ['id' => 'cred_1', 'transports' => ['internal']],
            ['id' => 'cred_2'],
        ];

        $options = WebAuthnEngine::generateRequestOptions($allowCreds, 'localhost');
        $this->assertArrayHasKey('challenge', $options);
        $this->assertSame('localhost', $options['rpId']);
        $this->assertCount(2, $options['allowCredentials']);
        $this->assertSame('cred_1', $options['allowCredentials'][0]['id']);
    }
}
