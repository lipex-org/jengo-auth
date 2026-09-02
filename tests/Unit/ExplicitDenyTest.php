<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Auth\Entities\User;
use Jengo\Auth\Models\UserModel;
use Tests\TestCase;
use Vima\Core\Role\Entities\Role;

class ExplicitDenyTest extends TestCase
{
    public function testExplicitDenyOverridesRoleGrant(): void
    {
        $userModel = new UserModel();
        $user = new User(['username' => 'probationuser', 'active' => 1]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $auth = auth();

        $auth->permissions()->create('posts.publish');
        $auth->roles()->save(new Role(name: 'publisher'));
        $auth->role('publisher')->permissions()->add('posts.publish');

        // Assign role to user
        $auth->user($user)->grant()->role('publisher');

        // Verify granted
        $this->assertTrue($auth->can('posts.publish', $user));

        // Explicitly deny permission
        $auth->user($user)->deny()->permission('posts.publish', 'User on probation');

        // Verify explicit deny takes precedence
        $this->assertFalse($auth->can('posts.publish', $user));

        // Undeny restores permission
        $auth->user($user)->undeny()->permission('posts.publish');
        $this->assertTrue($auth->can('posts.publish', $user));
    }

    public function testExplicitDenyOverridesDirectGrant(): void
    {
        $userModel = new UserModel();
        $user = new User(['username' => 'directdenyuser', 'active' => 1]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $auth = auth();
        $auth->permissions()->create('finance.refund');

        $auth->user($user)->grant()->permission('finance.refund');
        $this->assertTrue($auth->can('finance.refund', $user));

        // Explicit deny
        $auth->user($user)->deny()->permission('finance.refund', 'Account suspended from refunds');
        $this->assertFalse($auth->can('finance.refund', $user));
    }
}
