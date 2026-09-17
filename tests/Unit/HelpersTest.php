<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Auth\Entities\User;
use Jengo\Auth\Models\UserModel;
use Jengo\Auth\Support\AuthManager;
use Tests\TestCase;
use Vima\Core\VimaManager;

class HelpersTest extends TestCase
{
    public function testAuthAndVimaHelpers(): void
    {
        $this->assertInstanceOf(AuthManager::class, auth());
        $this->assertInstanceOf(VimaManager::class, vima());
    }

    public function testCanHelperDelegatesToAuth(): void
    {
        $this->assertFalse(can('articles.view'));

        $suffix = bin2hex(random_bytes(4));
        $userModel = new UserModel();
        $user = new User(['username' => 'helper_user_' . $suffix, 'active' => 1]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        auth()->login($user);
        auth()->permissions()->create('articles.view');
        auth()->user($user)->grant()->permission('articles.view');

        $this->assertTrue(can('articles.view'));
        $this->assertFalse(can('articles.delete'));
    }

    public function testCanAnyHelper(): void
    {
        $this->assertFalse(can_any(['perm1', 'perm2']));

        $suffix = bin2hex(random_bytes(4));
        $userModel = new UserModel();
        $user = new User(['username' => 'any_user_' . $suffix, 'active' => 1]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        auth()->login($user);
        auth()->permissions()->create('perm.read');
        auth()->permissions()->create('perm.write');
        auth()->permissions()->create('perm.delete');

        auth()->user($user)->grant()->permission('perm.read');

        // One of them is granted
        $this->assertTrue(can_any(['perm.read', 'perm.delete']));
        // None of them are granted
        $this->assertFalse(can_any(['perm.write', 'perm.delete']));
    }

    public function testCanAllHelper(): void
    {
        $this->assertFalse(can_all(['perm1', 'perm2']));

        $suffix = bin2hex(random_bytes(4));
        $userModel = new UserModel();
        $user = new User(['username' => 'all_user_' . $suffix, 'active' => 1]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        auth()->login($user);
        auth()->permissions()->create('task.create');
        auth()->permissions()->create('task.assign');
        auth()->permissions()->create('task.delete');

        auth()->user($user)->grant()->permission('task.create');
        auth()->user($user)->grant()->permission('task.assign');

        // Both are granted
        $this->assertTrue(can_all(['task.create', 'task.assign']));
        // One is missing
        $this->assertFalse(can_all(['task.create', 'task.assign', 'task.delete']));
    }

    public function testAuthUrlHelper(): void
    {
        $loginUrl = auth_url('login');
        $this->assertStringContainsString('login', $loginUrl);

        $logoutUrl = auth_url('logout');
        $this->assertStringContainsString('logout', $logoutUrl);

        $registerUrl = auth_url('register');
        $this->assertStringContainsString('register', $registerUrl);

        $resetUrl = auth_url('reset-password', 'sample-token-123');
        $this->assertStringContainsString('reset-password/sample-token-123', $resetUrl);

        $magicUrl = auth_url('magic-link.verify', 'magic-token-456');
        $this->assertStringContainsString('magic-link/verify/magic-token-456', $magicUrl);
    }
}
