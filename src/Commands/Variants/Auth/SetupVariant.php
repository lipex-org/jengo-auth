<?php

declare(strict_types=1);

namespace Jengo\Auth\Commands\Variants\Auth;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Commands\Contracts\CommandVariantInterface;

class SetupVariant implements CommandVariantInterface
{
    public static function name(): string
    {
        return 'setup';
    }

    public static function description(): string
    {
        return 'Interactive setup wizard for Jengo Auth.';
    }

    public function arguments(): array
    {
        return [];
    }

    public function options(): array
    {
        return [
            '--api'     => 'Publish API authentication routes',
            '--inertia' => 'Publish Inertia authentication controllers and views',
        ];
    }

    public function run(array $params): void
    {
        CLI::write("=== Jengo Auth Setup Wizard ===", 'cyan');
        CLI::newLine();

        // 1. Copy config
        $targetConfig = APPPATH . 'Config/Auth.php';
        if (! file_exists($targetConfig)) {
            $sourceConfig = __DIR__ . '/../../Config/Auth.php';
            copy($sourceConfig, $targetConfig);
            CLI::write("  " . CLI::color("✔", "green") . " Published Config/Auth.php");
        } else {
            CLI::write("  " . CLI::color("●", "yellow") . " Config/Auth.php already exists.");
        }

        CLI::newLine();
        CLI::write("Next steps:", 'yellow');
        CLI::write("  1. Run migrations: " . CLI::color("php spark migrate", 'cyan'));
        CLI::write("  2. Create a superadmin user or role: " . CLI::color("php spark jengo:auth grant <userId> admin", 'cyan'));
        CLI::newLine();
    }
}
