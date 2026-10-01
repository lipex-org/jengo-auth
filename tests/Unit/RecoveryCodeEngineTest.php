<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Auth\TwoFactor\Engines\RecoveryCodeEngine;

final class RecoveryCodeEngineTest extends CIUnitTestCase
{
    public function testGenerateRecoveryCodes(): void
    {
        $codes = RecoveryCodeEngine::generate(8);
        $this->assertCount(8, $codes);

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{4}-[a-f0-9]{4}$/', $code);
        }
    }

    public function testHashAndConsumeRecoveryCode(): void
    {
        $plainCodes = RecoveryCodeEngine::generate(5);
        $hashedCodes = RecoveryCodeEngine::hashCodes($plainCodes);

        $this->assertCount(5, $hashedCodes);

        // Verify first code
        $codeToUse = $plainCodes[0];
        $this->assertTrue(RecoveryCodeEngine::verifyAndConsume($codeToUse, $hashedCodes));
        $this->assertCount(4, $hashedCodes);

        // Trying to use same code again must fail (consumed/burned)
        $this->assertFalse(RecoveryCodeEngine::verifyAndConsume($codeToUse, $hashedCodes));

        // Trying with invalid code must fail
        $this->assertFalse(RecoveryCodeEngine::verifyAndConsume('invalid-code', $hashedCodes));
        $this->assertCount(4, $hashedCodes);
    }
}
