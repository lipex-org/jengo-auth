<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Auth\Entities\User;
use Jengo\Auth\Entities\UserIdentity;
use Jengo\Auth\Entities\UserToken;
use Jengo\Auth\Models\UserIdentityModel;
use Jengo\Auth\Models\UserModel;
use Jengo\Auth\Models\UserTokenModel;
use Tests\TestCase;

class UserModelsAndIdentitiesTest extends TestCase
{
    public function testUserIdentityModelLifecycle(): void
    {
        $userModel = new UserModel();
        $identityModel = new UserIdentityModel();

        $uid = uniqid();
        $user = new User(['username' => 'iduser_' . $uid, 'email' => 'id_' . $uid . '@example.com']);
        $userId = (int) $userModel->insert($user);

        $identity = new UserIdentity([
            'user_id' => $userId,
            'type'    => 'email_password',
            'name'    => 'Password',
            'secret'  => 'id_' . $uid . '@example.com',
            'secret2' => password_hash('SecretPass123!', PASSWORD_BCRYPT),
        ]);

        $identityId = (int) $identityModel->insert($identity);
        $this->assertGreaterThan(0, $identityId);

        $found = $identityModel->where('user_id', $userId)->where('secret', 'id_' . $uid . '@example.com')->first();
        $this->assertNotNull($found);
        $this->assertSame('id_' . $uid . '@example.com', $found->secret);

        $allIdentities = $identityModel->where('user_id', $userId)->findAll();
        $this->assertCount(1, $allIdentities);
    }

    public function testUserTokenModelLifecycle(): void
    {
        $userModel = new UserModel();
        $tokenModel = new UserTokenModel();

        $uid = uniqid();
        $user = new User(['username' => 'tokenuser_' . $uid, 'email' => 'token_' . $uid . '@example.com']);
        $userId = (int) $userModel->insert($user);

        $rawToken = 'test_plain_token_' . $uid;
        $hashed = hash('sha256', $rawToken);

        $token = new UserToken([
            'user_id'    => $userId,
            'name'       => 'Mobile App Token',
            'token_hash' => $hashed,
            'abilities'  => ['read', 'profile'],
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        $tokenId = (int) $tokenModel->insert($token);
        $this->assertGreaterThan(0, $tokenId);

        $foundToken = $tokenModel->findByPlainTextToken($rawToken);
        $this->assertNotNull($foundToken);
        $this->assertSame('Mobile App Token', $foundToken->name);
        $this->assertFalse($foundToken->isExpired());
        $this->assertTrue($foundToken->can('read'));
        $this->assertFalse($foundToken->can('admin'));
    }
}
