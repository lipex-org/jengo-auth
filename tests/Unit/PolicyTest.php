<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Auth\Entities\User;
use Jengo\Auth\Models\UserModel;
use Tests\TestCase;
use Vima\Core\Policy\Attributes\MapToPermission;
use Vima\Core\Policy\Contracts\PolicyInterface;
use Vima\Core\Policy\DTOs\AccessContext;
use Vima\Core\Role\Entities\Role;
use Vima\Core\Vima;

class DummyPost
{
    public int $id;
    public int $user_id;

    public function __construct(int $id, int $userId)
    {
        $this->id = $id;
        $this->user_id = $userId;
    }
}

class DummyPostPolicy implements PolicyInterface
{
    public static function getResource(): string
    {
        return DummyPost::class;
    }

    #[MapToPermission('update')]
    public function update(AccessContext $context, DummyPost $post): bool
    {
        return (int) $context->user->id === (int) $post->user_id;
    }

    #[MapToPermission('delete')]
    public function delete(AccessContext $context, DummyPost $post): bool
    {
        return (int) $context->user->id === (int) $post->user_id;
    }
}

class PolicyTest extends TestCase
{
    public function testPolicyAuthorAuthorization(): void
    {
        $userModel = new UserModel();

        $author = new User(['username' => 'author', 'active' => 1]);
        $authorId = $userModel->insert($author);
        $author->id = (int) $authorId;

        $otherUser = new User(['username' => 'other', 'active' => 1]);
        $otherId = $userModel->insert($otherUser);
        $otherUser->id = (int) $otherId;

        $post = new DummyPost(1, (int) $author->id);

        Vima::policies()->registerClass(DummyPost::class, DummyPostPolicy::class);

        // Author can update
        $this->assertTrue(auth()->can('update', $post, $author));

        // Other user cannot update
        $this->assertFalse(auth()->can('update', $post, $otherUser));
    }

    public function testPolicySuperAdminBypass(): void
    {
        $userModel = new UserModel();
        $admin = new User(['username' => 'admin', 'active' => 1]);
        $adminId = $userModel->insert($admin);
        $admin->id = (int) $adminId;

        auth()->roles()->save(new Role(name: 'superadmin'));
        auth()->user($admin)->grant()->role('superadmin');

        $post = new DummyPost(1, 999); // post owned by someone else

        Vima::policies()->registerClass(DummyPost::class, DummyPostPolicy::class);

        // Superadmin bypasses policy
        $this->assertTrue(auth()->can('update', $post, $admin));
    }
}
