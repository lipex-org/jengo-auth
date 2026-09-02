<?php

declare(strict_types=1);

namespace Jengo\Auth\Commands\Variants\Auth;

use CodeIgniter\CLI\CLI;
use Config\Database;
use Config\Services;
use Jengo\Auth\Entities\User;
use Jengo\Auth\Entities\UserIdentity;
use Jengo\Auth\Models\UserIdentityModel;
use Jengo\Auth\Models\UserModel;
use Jengo\Base\Commands\Contracts\CommandVariantInterface;
use Vima\Core\Role\Entities\Role;

class ImportShieldVariant implements CommandVariantInterface
{
    public static function name(): string
    {
        return 'import:shield';
    }

    public static function description(): string
    {
        return 'Migrate users, identities, groups, and permissions from CodeIgniter Shield.';
    }

    public function arguments(): array
    {
        return [];
    }

    public function options(): array
    {
        return [
            '--dry-run' => 'Simulate the import without writing changes to the database',
        ];
    }

    public function run(array $params): void
    {
        $group = (defined('ENVIRONMENT') && ENVIRONMENT === 'testing') ? 'tests' : null;
        $db = Database::connect($group);
        $isDryRun = CLI::getOption('dry-run') !== null;
        $auth = Services::auth();

        CLI::write("=== CodeIgniter Shield to Jengo Auth Importer ===", 'cyan');
        if ($isDryRun) {
            CLI::write("  [DRY-RUN MODE ACTIVE]", 'yellow');
        }

        // Check if shield tables exist
        $hasShieldIdentities = $db->tableExists('auth_identities');

        if (! $hasShieldIdentities) {
            CLI::error("No CodeIgniter Shield tables (auth_identities) found in database.");
            return;
        }

        $userCount = 0;
        $identityCount = 0;
        $roleCount = 0;
        $permCount = 0;

        // 1. Import Users
        $shieldUsers = $db->table('users')->get()->getResultArray();
        $userModel = new UserModel();

        foreach ($shieldUsers as $su) {
            $userCount++;
            if (! $isDryRun) {
                $existing = $userModel->find($su['id']);
                if (! $existing) {
                    $user = new User([
                        'id'             => $su['id'],
                        'username'       => $su['username'] ?? null,
                        'status'         => $su['status'] ?? 'active',
                        'status_message' => $su['status_message'] ?? null,
                        'active'         => $su['active'] ?? 1,
                        'last_active'    => $su['last_active'] ?? null,
                        'created_at'     => $su['created_at'] ?? null,
                        'updated_at'     => $su['updated_at'] ?? null,
                        'deleted_at'     => $su['deleted_at'] ?? null,
                    ]);
                    $userModel->insert($user);
                }
            }
        }
        CLI::write("  " . CLI::color("✔", "green") . " Scanned {$userCount} users.");

        // 2. Import Identities
        $shieldIdentities = $db->table('auth_identities')->get()->getResultArray();
        $identityModel = new UserIdentityModel();

        foreach ($shieldIdentities as $si) {
            $identityCount++;
            if (! $isDryRun) {
                $existing = $identityModel->where('user_id', $si['user_id'])->where('type', $si['type'])->first();
                if (! $existing) {
                    $identity = new UserIdentity([
                        'user_id'      => $si['user_id'],
                        'type'         => $si['type'],
                        'name'         => $si['name'] ?? null,
                        'secret'       => $si['secret'],
                        'secret2'      => $si['secret2'] ?? null,
                        'expires'      => $si['expires'] ?? null,
                        'extra'        => $si['extra'] ?? null,
                        'force_reset'  => $si['force_reset'] ?? 0,
                        'last_used_at' => $si['last_used_at'] ?? null,
                        'created_at'   => $si['created_at'] ?? null,
                        'updated_at'   => $si['updated_at'] ?? null,
                    ]);
                    $identityModel->insert($identity);
                }
            }
        }
        CLI::write("  " . CLI::color("✔", "green") . " Scanned {$identityCount} user identities.");

        // 3. Import Groups as Roles
        if ($db->tableExists('auth_groups_users')) {
            $groupUsers = $db->table('auth_groups_users')->get()->getResultArray();

            foreach ($groupUsers as $gu) {
                $groupName = $gu['group'];
                if (! $isDryRun) {
                    $auth->roles()->save(new Role(name: $groupName));
                    $auth->user((string) $gu['user_id'])->grant()->role($groupName);
                    $roleCount++;
                }
            }
            CLI::write("  " . CLI::color("✔", "green") . " Imported {$roleCount} role memberships.");
        }

        // 4. Import Permissions
        if ($db->tableExists('auth_permissions_users')) {
            $permUsers = $db->table('auth_permissions_users')->get()->getResultArray();

            foreach ($permUsers as $pu) {
                $permName = $pu['permission'];
                if (! $isDryRun) {
                    $auth->permissions()->create($permName);
                    $auth->user((string) $pu['user_id'])->grant()->permission($permName);
                    $permCount++;
                }
            }
            CLI::write("  " . CLI::color("✔", "green") . " Imported {$permCount} user direct permissions.");
        }

        CLI::newLine();
        CLI::write("Migration completed successfully!", 'green');
    }
}
