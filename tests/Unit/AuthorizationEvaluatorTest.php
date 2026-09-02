<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Auth\Entities\User;
use Jengo\Auth\Models\UserModel;
use Tests\TestCase;
use Vima\Core\Role\Entities\Role;

class AuthorizationEvaluatorTest extends TestCase
{
    public function testSuperAdminBypass(): void
    {
        $userModel = new UserModel();
        $user = new User(['username' => 'superadmin', 'active' => 1]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $auth = auth();
        $auth->roles()->save(new Role(name: 'superadmin'));
        $auth->user($user)->grant()->role('superadmin');

        $this->assertTrue($auth->isSuperAdmin($user));
        $this->assertTrue($auth->can('any.random.action', $user));
        $this->assertTrue($auth->can('database.drop', $user));
    }

    public function testDirectUserPermission(): void
    {
        $userModel = new UserModel();
        $user = new User(['username' => 'directuser', 'active' => 1]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $auth = auth();
        $auth->permissions()->create('reports.export');

        $this->assertFalse($auth->can('reports.export', $user));

        $auth->user($user)->grant()->permission('reports.export');

        $this->assertTrue($auth->can('reports.export', $user));
        $this->assertFalse($auth->can('reports.delete', $user));
    }

    public function testRoleAndHierarchicalParentInheritance(): void
    {
        $userModel = new UserModel();
        $user = new User(['username' => 'editoruser', 'active' => 1]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $auth = auth();

        // 1. Create permissions
        $auth->permissions()->create('posts.view');
        $auth->permissions()->create('posts.edit');

        // 2. Create roles
        $auth->roles()->save(new Role(name: 'viewer'));
        $auth->roles()->save(new Role(name: 'editor'));

        // 3. Attach permissions to roles
        $auth->role('viewer')->permissions()->add('posts.view');
        $auth->role('editor')->permissions()->add('posts.edit');

        // 4. Editor inherits from Viewer
        $auth->role('editor')->parents()->add('viewer');

        // 5. Assign Editor role to user
        $auth->user($user)->grant()->role('editor');

        // User should have both posts.edit (direct role) AND posts.view (inherited role parent)
        $this->assertTrue($auth->can('posts.edit', $user));
        $this->assertTrue($auth->can('posts.view', $user));
        $this->assertFalse($auth->can('posts.delete', $user));
    }
}
