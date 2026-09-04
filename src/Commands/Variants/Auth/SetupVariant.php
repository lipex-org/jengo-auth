<?php

declare(strict_types=1);

namespace Jengo\Auth\Commands\Variants\Auth;

use CodeIgniter\CLI\CLI;
use Config\Services;
use Jengo\Base\Commands\Contracts\CommandVariantInterface;
use Vima\CodeIgniter\Commands\VimaSetup;

class SetupVariant implements CommandVariantInterface
{
    public static function name(): string
    {
        return 'setup';
    }

    public static function description(): string
    {
        return 'Interactive setup wizard for Jengo Auth and Vima.';
    }

    public function arguments(): array
    {
        return [];
    }

    public function options(): array
    {
        return [
            '--overwrite' => 'Overwrite existing config and setup files',
        ];
    }

    public function run(array $params): void
    {
        $overwrite = (bool) (CLI::getOption('overwrite') ?? false);

        CLI::write('=== Jengo Auth & Vima Setup Wizard ===', 'cyan');
        CLI::newLine();

        // 1. Publish Jengo Auth config extending BaseAuth
        $this->publishConfig($overwrite);

        // 2. Run Vima Setup (publishes Config/Vima.php and Libraries/Vima/Setup.php)
        CLI::newLine();
        CLI::write('Running Vima Setup...', 'cyan');
        try {
            command('vima:setup' . ($overwrite ? ' --overwrite' : ''));
        } catch (\Throwable $e) {
            $vimaSetup = new VimaSetup(
                Services::logger(),
                Services::commands()
            );
            $vimaSetup->run($params);
        }

        // 3. Automatically publish routes in app/Config/Routes.php
        $this->publishRoutes();

        CLI::newLine();
        CLI::write('Next steps:', 'yellow');
        CLI::write('  1. Run migrations: ' . CLI::color('php spark migrate', 'cyan'));
        CLI::write('  2. Define roles & permissions in ' . CLI::color('app/Libraries/Vima/Setup.php', 'green'));
        CLI::write('  3. Sync authorization schema to database: ' . CLI::color('php spark vima:sync', 'cyan'));
        CLI::write('  4. (Optional) Generate TS definitions for frontend: ' . CLI::color('php spark vima:maps:generate --ts', 'cyan'));
        CLI::newLine();
    }

    /**
     * Publishes the Config/Auth.php file extending Jengo\Auth\Config\Auth as BaseAuth.
     */
    protected function publishConfig(bool $overwrite): void
    {
        $targetConfig = APPPATH . 'Config/Auth.php';
        $sourceConfig = __DIR__ . '/../../../Config/Auth.php';

        if (! file_exists($sourceConfig)) {
            CLI::error('Source Config/Auth.php not found.');
            return;
        }

        if (file_exists($targetConfig) && ! $overwrite) {
            CLI::write('  ' . CLI::color('●', 'yellow') . ' Config/Auth.php already exists.');
            return;
        }

        $content = file_get_contents($sourceConfig);
        if ($content === false) {
            CLI::error('Failed to read source Config/Auth.php');
            return;
        }

        // Adjust namespace, extend BaseAuth, and import base class
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

        $content = str_replace($search, $replace, $content);
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        $targetDir = dirname($targetConfig);
        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        if (file_put_contents($targetConfig, $content) !== false) {
            CLI::write('  ' . CLI::color('✔', 'green') . ' Published Config/Auth.php (extending BaseAuth)');
        } else {
            CLI::error('Failed to publish Config/Auth.php');
        }
    }

    /**
     * Appends auth route publishing to Config/Routes.php if not present.
     */
    protected function publishRoutes(): void
    {
        $routesPath = APPPATH . 'Config/Routes.php';
        if (! file_exists($routesPath)) {
            return;
        }

        $content = file_get_contents($routesPath);
        if (
            str_contains($content, "service('auth')->routes") ||
            str_contains($content, 'auth()->routes')
        ) {
            CLI::write(
                '  ' .
                    CLI::color('●', 'yellow') .
                    ' Auth routes already registered in Config/Routes.php.'
            );
            return;
        }

        $routesSnippet =
            "\n// Jengo Auth Authentication Routes\nservice('auth')->routes(\$routes);\n";
        file_put_contents($routesPath, $content . $routesSnippet);
        CLI::write(
            '  ' .
                CLI::color('✔', 'green') .
                ' Added authentication routes to Config/Routes.php'
        );
    }
}
