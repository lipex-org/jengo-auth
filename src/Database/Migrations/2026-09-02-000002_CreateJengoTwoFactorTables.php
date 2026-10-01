<?php

declare(strict_types=1);

namespace Jengo\Auth\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateJengoTwoFactorTables extends Migration
{
    public function up(): void
    {
        // 1. User Two-Factor Settings & Secrets (TOTP, Recovery Codes, Email OTP)
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'totp_secret'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'totp_enabled'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'recovery_codes'    => ['type' => 'TEXT', 'null' => true],
            'email_otp_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('user_id');
        $this->forge->createTable('auth_user_two_factor', true);

        // 2. User Passkeys & Hardware Security Keys (FIDO2 / WebAuthn)
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'Passkey'],
            'credential_id' => ['type' => 'VARCHAR', 'constraint' => 255],
            'public_key'    => ['type' => 'TEXT'],
            'counter'       => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
            'aaguid'        => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'transports'    => ['type' => 'TEXT', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'last_used_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->addUniqueKey('credential_id');
        $this->forge->createTable('auth_user_passkeys', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('auth_user_passkeys', true);
        $this->forge->dropTable('auth_user_two_factor', true);
    }
}
