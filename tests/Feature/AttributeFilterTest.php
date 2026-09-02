<?php

declare(strict_types=1);

namespace Tests\Feature;

use Config\Services;
use Jengo\Auth\Attributes\Authenticate;
use Jengo\Auth\Attributes\Can;
use Jengo\Auth\Attributes\Guest;
use Jengo\Auth\Attributes\Role;
use Jengo\Auth\Entities\User;
use Jengo\Auth\Filters\AuthFilter;
use Jengo\Auth\Models\UserModel;
use Tests\TestCase;
use Vima\Core\Role\Entities\Role as VimaRole;

#[Authenticate]
class DummySecuredController
{
    #[Role('admin')]
    public function adminOnly()
    {
        return 'admin-ok';
    }

    #[Can('posts.create')]
    public function createPost()
    {
        return 'create-ok';
    }
}

class DummyGuestController
{
    #[Guest]
    public function login()
    {
        return 'login-form';
    }
}

class AttributeFilterTest extends TestCase
{
    public function testUnauthenticatedUserIsBlocked(): void
    {
        $filter = new AuthFilter();
        $request = Services::request();
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $result = $filter->checkControllerAttributes(DummySecuredController::class, 'adminOnly', $request);

        $this->assertNotNull($result);
        $this->assertSame(401, $result->getStatusCode());

        unset($_SERVER['HTTP_ACCEPT']);
    }

    public function testRoleAuthorizationFilter(): void
    {
        $userModel = new UserModel();
        $user = new User(['username' => 'regularuser', 'active' => 1]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $auth = auth();
        $auth->login($user);

        $filter = new AuthFilter();
        $request = Services::request();
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        // Regular user blocked with 403
        $result = $filter->checkControllerAttributes(DummySecuredController::class, 'adminOnly', $request);
        $this->assertNotNull($result);
        $this->assertSame(403, $result->getStatusCode());

        // Grant admin role
        $auth->roles()->save(new VimaRole(name: 'admin'));
        $auth->user($user)->grant()->role('admin');

        // Regular user with admin role is allowed (returns null = proceed)
        $allowedResult = $filter->checkControllerAttributes(DummySecuredController::class, 'adminOnly', $request);
        $this->assertNull($allowedResult);

        unset($_SERVER['HTTP_ACCEPT']);
    }
}
