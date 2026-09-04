<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Auth\Commands\Variants\Auth\SetupVariant;
use Tests\TestCase;

class SetupVariantTest extends TestCase
{
    public function testSetupVariantMetadata(): void
    {
        $this->assertSame('setup', SetupVariant::name());
        $this->assertNotEmpty(SetupVariant::description());

        $variant = new SetupVariant();
        $this->assertArrayHasKey('--overwrite', $variant->options());
    }

    public function testConfigPublishingTransformation(): void
    {
        $sourceConfig = __DIR__ . '/../../src/Config/Auth.php';
        $this->assertFileExists($sourceConfig);

        $content = file_get_contents($sourceConfig);

        $search = [
            'namespace Jengo\Auth\Config;',
            "use CodeIgniter\Config\BaseConfig;\n",
            'use CodeIgniter\Config\BaseConfig;',
            'class Auth extends BaseConfig',
        ];
        $replace = [
            "namespace Config;\n\nuse Jengo\Auth\Config\Auth as BaseAuth;",
            '',
            '',
            'class Auth extends BaseAuth',
        ];

        $transformed = str_replace($search, $replace, $content);
        $transformed = preg_replace("/\n{3,}/", "\n\n", $transformed);

        $this->assertStringContainsString('namespace Config;', $transformed);
        $this->assertStringContainsString('use Jengo\Auth\Config\Auth as BaseAuth;', $transformed);
        $this->assertStringContainsString('class Auth extends BaseAuth', $transformed);
        $this->assertStringNotContainsString('class Auth extends BaseConfig', $transformed);
    }
}
