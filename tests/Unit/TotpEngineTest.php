<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Auth\TwoFactor\Engines\TotpEngine;

final class TotpEngineTest extends CIUnitTestCase
{
    public function testBase32EncodingAndDecoding(): void
    {
        $raw = 'Hello Jengo Two-Factor!';
        $encoded = TotpEngine::base32Encode($raw);
        $decoded = TotpEngine::base32Decode($encoded);

        $this->assertSame($raw, $decoded);
    }

    public function testSecretGeneration(): void
    {
        $secret = TotpEngine::generateSecret(20);
        $this->assertNotEmpty($secret);
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function testGenerateAndVerifyCode(): void
    {
        $secret = TotpEngine::generateSecret();
        $code = TotpEngine::generateCode($secret);

        $this->assertSame(6, strlen($code));
        $this->assertTrue(TotpEngine::verify($code, $secret));
        $this->assertFalse(TotpEngine::verify('000000', $secret));
    }

    public function testWindowDriftTolerance(): void
    {
        $secret = TotpEngine::generateSecret();
        $now = time();

        // Code generated 25 seconds ago (within current/previous 30s window)
        $pastCode = TotpEngine::generateCode($secret, $now - 25);
        $this->assertTrue(TotpEngine::verify($pastCode, $secret, window: 1, timestamp: $now));

        // Code generated 120 seconds ago (outside window of 1)
        $ancientCode = TotpEngine::generateCode($secret, $now - 120);
        $this->assertFalse(TotpEngine::verify($ancientCode, $secret, window: 1, timestamp: $now));
    }

    public function testOtpAuthUriGeneration(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $uri = TotpEngine::getOtpAuthUri($secret, 'alice@example.com', 'Acme Corp');

        $this->assertStringStartsWith('otpauth://totp/Acme%20Corp:alice%40example.com', $uri);
        $this->assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        $this->assertStringContainsString('issuer=Acme%20Corp', $uri);
    }
}
