<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Auth\Entities\User;
use Jengo\Auth\Entities\UserIdentity;
use Jengo\Auth\Models\UserIdentityModel;
use Jengo\Auth\Models\UserModel;
use Tests\TestCase;
use Vima\Core\Role\Entities\Role as VimaRole;

class UserEntityTest extends TestCase
{
    public function testUserBanAndActiveState(): void
    {
        $user = new User([
            'username' => 'bannable_user_' . bin2hex(random_bytes(4)),
            'active'   => 1,
            'status'   => 'active',
        ]);

        $this->assertFalse($user->isBanned());
        $this->assertTrue($user->isActive());

        // Ban user
        $user->ban('Spamming activity');

        $this->assertTrue($user->isBanned());
        $this->assertFalse($user->isActive());
        $this->assertSame('banned', $user->status);
        $this->assertFalse((bool) $user->active);
        $this->assertSame('Spamming activity', $user->status_message);

        // Unban user
        $user->unban();

        $this->assertFalse($user->isBanned());
        $this->assertTrue($user->isActive());
        $this->assertSame('active', $user->status);
        $this->assertTrue((bool) $user->active);
        $this->assertNull($user->status_message);
    }

    public function testGetEmailFromAttributeOrIdentity(): void
    {
        // 1. Direct attribute email
        $userWithEmailAttr = new User([
            'id'       => 10,
            'username' => 'direct_email_user',
            'email'    => 'direct@example.com',
        ]);
        $this->assertSame('direct@example.com', $userWithEmailAttr->getEmail());

        // 2. Identity email
        $suffix = bin2hex(random_bytes(4));
        $userModel = new UserModel();
        $user = new User([
            'username' => 'identity_email_user_' . $suffix,
            'active'   => 1,
        ]);
        $userId = $userModel->insert($user);
        $user->id = (int) $userId;

        $identityModel = new UserIdentityModel();
        $identityModel->insert(new UserIdentity([
            'user_id' => $user->id,
            'type'    => 'email_password',
            'name'    => "identity_{$suffix}@example.com",
            'secret'  => 'hash',
        ]));

        $this->assertSame("identity_{$suffix}@example.com", $user->getEmail());
    }

    public function testGetIdAndVimaGetId(): void
    {
        $user = new User(['id' => 777]);
        $this->assertSame(777, $user->getId());
        $this->assertSame(777, $user->vimaGetId());
    }

    public function testRoleAndSuperAdminChecks(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $userModel = new UserModel();
        $user = new User(['username' => 'role_user_' . $suffix, 'active' => 1]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $auth = auth();
        $this->assertFalse($user->hasRole('admin'));
        $this->assertFalse($user->isSuperAdmin());

        // Create and grant admin role
        $auth->roles()->save(new VimaRole(name: 'admin'));
        $user->grant()->role('admin');
        $this->assertTrue($user->hasRole('admin'));

        // Create and grant superadmin role
        $auth->roles()->save(new VimaRole(name: 'superadmin'));
        $user->grant()->role('superadmin');
        $this->assertTrue($user->isSuperAdmin());
    }

    public function testCanAndCannotChecks(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $userModel = new UserModel();
        $user = new User(['username' => 'perm_user_' . $suffix, 'active' => 1]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        auth()->permissions()->create('articles.publish');

        $this->assertFalse($user->can('articles.publish'));
        $this->assertTrue($user->cannot('articles.publish'));

        $user->grant()->permission('articles.publish');

        $this->assertTrue($user->can('articles.publish'));
        $this->assertFalse($user->cannot('articles.publish'));
    }

    public function testFluentBuilderAccessors(): void
    {
        $user = new User(['id' => 100, 'username' => 'fluent_user']);

        $this->assertInstanceOf(\Vima\Core\User\Fluent\UserGrant::class, $user->grant());
        $this->assertInstanceOf(\Vima\Core\User\Fluent\UserDeny::class, $user->deny());
        $this->assertInstanceOf(\Vima\Core\User\Fluent\UserUndeny::class, $user->undeny());
        $this->assertInstanceOf(\Vima\Core\User\Fluent\UserRevoke::class, $user->revoke());
        $this->assertInstanceOf(\Vima\Core\User\Fluent\UserHas::class, $user->has());
        $this->assertInstanceOf(\Vima\Core\User\Fluent\UserIs::class, $user->is());
        $this->assertInstanceOf(\Vima\Core\User\Fluent\UserGet::class, $user->get());
    }

    public function testCreateTokenFromEntity(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $userModel = new UserModel();
        $user = new User(['username' => 'token_creator_' . $suffix, 'active' => 1]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $tokenResult = $user->createToken('Device Token', ['posts.read']);
        $this->assertNotEmpty($tokenResult->plainTextToken);
        $this->assertSame('Device Token', $tokenResult->accessToken->name);
        $this->assertTrue($tokenResult->accessToken->can('posts.read'));
    }
}
