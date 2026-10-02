<?php

declare(strict_types=1);

namespace Jengo\Auth\Installers;

use CodeIgniter\CLI\CLI;
use Config\Services;
use Jengo\Auth\Modifiers\InertiaModifier;
use Jengo\Auth\Modifiers\StandardViewModifier;
use Jengo\Base\Installers\Contracts\AbstractInstaller;
use Jengo\Base\Tooling\Modifier\ClassModifier;
use Vima\CodeIgniter\Commands\VimaSetup;

class AuthInstaller extends AbstractInstaller
{
    public static function name(): string
    {
        return 'auth';
    }

    public static function description(): string
    {
        return 'Setup the Jengo Auth configurations, Vima permissions, and authentication routing';
    }

    public static function reasonForSkipping(): string
    {
        return 'Jengo Auth is already configured.';
    }

    public function shouldRun(): bool
    {
        $targetConfig = APPPATH . 'Config/Auth.php';
        $routesFile = APPPATH . 'Config/Routes.php';

        $hasConfig = file_exists($targetConfig);
        $hasRoute = file_exists($routesFile) && (
            str_contains((string) file_get_contents($routesFile), "service('auth')->routes") ||
            str_contains((string) file_get_contents($routesFile), 'auth()->routes')
        );

        return !$hasConfig || !$hasRoute;
    }

    public function install(): void
    {
        $this->addRun();

        CLI::write('Setting up Jengo Auth configurations, Vima, and routing...', 'cyan');

        $force = CLI::getOption('force') !== null || CLI::getOption('overwrite') !== null;
        $targetConfig = APPPATH . 'Config/Auth.php';
        $sourceConfig = dirname(__DIR__) . '/Config/Auth.php';

        // 1. Publish Auth Config
        if (file_exists($targetConfig) && !$force) {
            CLI::write("Configuration file already exists at [{$targetConfig}]. Use --force or --overwrite to replace.", 'yellow');
        } else {
            $content = file_get_contents($sourceConfig);
            if ($content !== false) {
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
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                if (file_put_contents($targetConfig, $content) !== false) {
                    CLI::write("Published config file to [{$targetConfig}]", 'green');
                } else {
                    CLI::error("Failed to write config file to [{$targetConfig}].");
                }
            } else {
                CLI::error("Failed to read source config file from [{$sourceConfig}].");
            }
        }

        // 2. Determine Kit and Apply Modifier / Publish Stubs
        $kit = $this->resolveKit();

        if ($kit !== null && file_exists($targetConfig)) {
            // Update Auth config to use InertiaModifier
            ClassModifier::fromFile($targetConfig)
                ->addUseStatement(InertiaModifier::class)
                ->upsertProperty('responseModifier', InertiaModifier::class)
                ->saveTo($targetConfig);

            CLI::write('  ' . CLI::color('✔', 'green') . ' Config/Auth.php updated with InertiaModifier.');

            // Publish component stubs to client directory
            $stubsSource = dirname(__DIR__, 2) . '/stubs/' . $kit;
            if (is_dir($stubsSource)) {
                $destination = 'resources/js/inertia/pages/auth';
                $this->publish($stubsSource, $destination);
                CLI::write("  " . CLI::color('✔', 'green') . " Published {$kit} auth stubs to {$destination}.");
            } else {
                CLI::write("  " . CLI::color('●', 'yellow') . " No stubs found for kit [{$kit}] at {$stubsSource}.");
            }
        } elseif (file_exists($targetConfig)) {
            // Ensure StandardViewModifier is set when no kit is provided
            ClassModifier::fromFile($targetConfig)
                ->addUseStatement(StandardViewModifier::class)
                ->upsertProperty('responseModifier', StandardViewModifier::class)
                ->saveTo($targetConfig);
        }

        // 3. Run Vima Setup
        CLI::newLine();
        CLI::write('Running Vima Setup...', 'cyan');
        try {
            command('vima:setup' . ($force ? ' --overwrite' : ''));
        } catch (\Throwable $e) {
            if (class_exists(VimaSetup::class)) {
                $vimaSetup = new VimaSetup(
                    Services::logger(),
                    Services::commands()
                );
                $vimaSetup->run([]);
            }
        }

        $this->addHelperToAutoload(["Jengo\Auth\Helpers\auth"]);
        $this->sanitizeAutoload();

        // 4. Automatically append routes registration to app/Config/Routes.php
        $routesFile = APPPATH . 'Config/Routes.php';
        if (file_exists($routesFile)) {
            $routesContent = (string) file_get_contents($routesFile);
            if (
                !str_contains($routesContent, "service('auth')->routes") &&
                !str_contains($routesContent, 'auth()->routes')
            ) {
                $routesContent .= "\n\n// Jengo Auth Authentication Routes\nservice('auth')->routes(\$routes);\n";
                if (file_put_contents($routesFile, $routesContent) !== false) {
                    CLI::write("Appended routes registration to [{$routesFile}]", 'green');
                } else {
                    CLI::error("Failed to append routes to [{$routesFile}].");
                }
            } else {
                CLI::write("Routes registration already present in [{$routesFile}]", 'yellow');
            }
        }

        CLI::newLine();
        CLI::write('Jengo Auth setup complete!', 'green');
    }

    /**
     * Resolves the frontend starter kit (react, vue, svelte) from CLI options if provided.
     */
    protected function resolveKit(): ?string
    {
        $kit = CLI::getOption('kit') ?? CLI::getOption('framework');
        if (is_string($kit)) {
            $kit = strtolower(trim($kit));
            if (in_array($kit, ['react', 'vue', 'svelte'], true)) {
                return $kit;
            }
        }

        return null;
    }

    /**
     * Sanitizes app/Config/Autoload.php to clean up any accidental double commas or syntax errors in $helpers.
     */
    protected function sanitizeAutoload(): void
    {
        $autoloadPath = APPPATH . 'Config/Autoload.php';
        if (!file_exists($autoloadPath)) {
            return;
        }

        $content = file_get_contents($autoloadPath);
        if ($content === false) {
            return;
        }

        $pattern = '/(public\s+\$helpers\s*=\s*\[)(.*?)(\];)/s';
        $sanitized = preg_replace_callback($pattern, function ($matches) {
            $helpers = preg_replace('/,(\s*,)+/', ',', $matches[2]);
            return $matches[1] . $helpers . $matches[3];
        }, $content);

        if ($sanitized !== null && $sanitized !== $content) {
            file_put_contents($autoloadPath, $sanitized);
        }
    }
}
