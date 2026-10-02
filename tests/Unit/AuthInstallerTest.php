<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\Mock\MockInputOutput;
use Jengo\Auth\Installers\AuthInstaller;
use ReflectionClass;
use Tests\TestCase;

final class AuthInstallerTest extends TestCase
{
    private string $testConfig;
    private string $testRoutes;
    private MockInputOutput $io;

    protected function setUp(): void
    {
        parent::setUp();

        $this->io = new MockInputOutput();
        CLI::setInputOutput($this->io);

        $this->testConfig = APPPATH . 'Config/Auth.php';
        $this->testRoutes = APPPATH . 'Config/Routes.php';

        if (!is_dir(APPPATH . 'Config')) {
            mkdir(APPPATH . 'Config', 0777, true);
        }
    }

    private function setCliOptions(array $options): void
    {
        $reflection = new ReflectionClass(CLI::class);
        $optionsProperty = $reflection->getProperty('options');
        $optionsProperty->setAccessible(true);
        $optionsProperty->setValue(null, $options);
    }

    protected function tearDown(): void
    {
        $this->setCliOptions([]);
        CLI::resetInputOutput();

        if (file_exists($this->testConfig)) {
            unlink($this->testConfig);
        }

        $stubsTarget = ROOTPATH . 'resources/js/inertia/pages/auth';
        if (is_dir($stubsTarget)) {
            system('rm -rf ' . escapeshellarg(ROOTPATH . 'resources'));
        }

        parent::tearDown();
    }

    public function testMetadata(): void
    {
        $this->assertSame('auth', AuthInstaller::name());
        $this->assertNotEmpty(AuthInstaller::description());
        $this->assertNotEmpty(AuthInstaller::reasonForSkipping());
        $this->assertSame([], AuthInstaller::dependencies());
    }

    public function testShouldRunWhenConfigMissing(): void
    {
        if (file_exists($this->testConfig)) {
            unlink($this->testConfig);
        }

        $installer = new AuthInstaller();
        $this->assertTrue($installer->shouldRun());
    }

    public function testInstallWithoutKitUsesStandardViewModifier(): void
    {
        if (file_exists($this->testConfig)) {
            unlink($this->testConfig);
        }

        if (!file_exists($this->testRoutes)) {
            file_put_contents($this->testRoutes, "<?php\nuse CodeIgniter\\Router\\RouteCollection;\n");
        }

        $this->setCliOptions([]);

        $installer = new AuthInstaller();
        $installer->install();

        $this->assertFileExists($this->testConfig);
        $configContent = file_get_contents($this->testConfig);
        $this->assertStringContainsString('class Auth extends BaseAuth', $configContent);
        $this->assertStringContainsString('StandardViewModifier', $configContent);

        $stubsTarget = ROOTPATH . 'resources/js/inertia/pages/auth';
        $this->assertDirectoryDoesNotExist($stubsTarget);

        $routesContent = file_get_contents($this->testRoutes);
        $this->assertStringContainsString("service('auth')->routes", $routesContent);
        $this->assertSame(1, $installer->runs);
    }

    public function testInstallWithReactKitUsesInertiaModifierAndPublishesStubs(): void
    {
        if (file_exists($this->testConfig)) {
            unlink($this->testConfig);
        }

        if (!file_exists($this->testRoutes)) {
            file_put_contents($this->testRoutes, "<?php\nuse CodeIgniter\\Router\\RouteCollection;\n");
        }

        $this->setCliOptions(['kit' => 'react']);

        $installer = new AuthInstaller();
        $installer->install();

        $this->assertFileExists($this->testConfig);
        $configContent = file_get_contents($this->testConfig);
        $this->assertStringContainsString('InertiaModifier', $configContent);

        $stubsTarget = ROOTPATH . 'resources/js/inertia/pages/auth';
        $this->assertDirectoryExists($stubsTarget);
        $this->assertFileExists($stubsTarget . '/login.tsx');
        $this->assertFileExists($stubsTarget . '/register.tsx');
        $this->assertFileExists($stubsTarget . '/forgot_password.tsx');
        $this->assertFileExists($stubsTarget . '/reset_password.tsx');
        $this->assertFileExists($stubsTarget . '/two_factor_settings.tsx');
    }

    public function testInstallWithVueKitPublishesVueStubs(): void
    {
        if (file_exists($this->testConfig)) {
            unlink($this->testConfig);
        }

        $this->setCliOptions(['kit' => 'vue']);

        $installer = new AuthInstaller();
        $installer->install();

        $this->assertFileExists($this->testConfig);
        $configContent = file_get_contents($this->testConfig);
        $this->assertStringContainsString('InertiaModifier', $configContent);

        $stubsTarget = ROOTPATH . 'resources/js/inertia/pages/auth';
        $this->assertDirectoryExists($stubsTarget);
        $this->assertFileExists($stubsTarget . '/login.vue');
        $this->assertFileExists($stubsTarget . '/register.vue');
    }

    public function testInstallWithSvelteKitPublishesSvelteStubs(): void
    {
        if (file_exists($this->testConfig)) {
            unlink($this->testConfig);
        }

        $this->setCliOptions(['kit' => 'svelte']);

        $installer = new AuthInstaller();
        $installer->install();

        $this->assertFileExists($this->testConfig);
        $configContent = file_get_contents($this->testConfig);
        $this->assertStringContainsString('InertiaModifier', $configContent);

        $stubsTarget = ROOTPATH . 'resources/js/inertia/pages/auth';
        $this->assertDirectoryExists($stubsTarget);
        $this->assertFileExists($stubsTarget . '/login.svelte');
        $this->assertFileExists($stubsTarget . '/register.svelte');
    }
}
