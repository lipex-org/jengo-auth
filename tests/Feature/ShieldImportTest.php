<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\Mock\MockInputOutput;
use Config\Database;
use Jengo\Auth\Commands\Variants\Auth\ImportShieldVariant;
use Tests\TestCase;

class ShieldImportTest extends TestCase
{
    public function testShieldImportMigration(): void
    {
        $db = Database::connect('tests');
        $forge = Database::forge('tests');

        // 1. Create mock Shield tables
        $forge->addField([
            'id'       => ['type' => 'INT', 'auto_increment' => true],
            'user_id'  => ['type' => 'INT'],
            'type'     => ['type' => 'VARCHAR', 'constraint' => 50],
            'name'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'secret'   => ['type' => 'VARCHAR', 'constraint' => 255],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('auth_identities', true);

        $forge->addField([
            'id'       => ['type' => 'INT', 'auto_increment' => true],
            'user_id'  => ['type' => 'INT'],
            'group'    => ['type' => 'VARCHAR', 'constraint' => 50],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('auth_groups_users', true);

        // 2. Insert mock Shield data
        $db->table('users')->insert([
            'id'       => 10,
            'username' => 'shielduser',
            'active'   => 1,
        ]);

        $db->table('auth_identities')->insert([
            'user_id' => 10,
            'type'    => 'email_password',
            'name'    => 'shield@example.com',
            'secret'  => '$2y$10$abcdefghijklmnopqrstuv',
        ]);

        $db->table('auth_groups_users')->insert([
            'user_id' => 10,
            'group'   => 'admin',
        ]);

        $mockIO = new MockInputOutput();
        CLI::setInputOutput($mockIO);
        // 3. Run import command variant
        $variant = new ImportShieldVariant();
        $variant->run([]);

        CLI::resetInputOutput();

        // 4. Verify imported data in Jengo Auth
        $auth = auth();
        $importedUser = $auth->getUserModel()->find(10);

        $this->assertNotNull($importedUser);
        $this->assertSame('shielduser', $importedUser->username);

        $identity = $auth->getUserIdentityModel()->where('user_id', 10)->where('type', 'email_password')->first();
        $this->assertNotNull($identity);
        $this->assertSame('shield@example.com', $identity->name);

        $this->assertTrue($auth->hasRole($importedUser, 'admin'));

        // Cleanup mock tables
        $forge->dropTable('auth_groups_users', true);
        $forge->dropTable('auth_identities', true);
    }
}
