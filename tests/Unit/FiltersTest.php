<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use Config\App;
use Config\Services;
use Jengo\Auth\Entities\User;
use Jengo\Auth\Filters\AuthFilter;
use Jengo\Auth\Filters\PermissionFilter;
use Jengo\Auth\Filters\RoleFilter;
use Jengo\Auth\Models\UserModel;
use Tests\TestCase;
use Vima\Core\Role\Entities\Role as VimaRole;

class FiltersTest extends TestCase
{
    protected function createRequest(string $uri = 'http://localhost/dashboard', array $headers = []): IncomingRequest
    {
        $request = new IncomingRequest(new App(), new URI($uri), null, new UserAgent());
        foreach ($headers as $name => $value) {
            $request->setHeader($name, $value);
        }
        return $request;
    }

    public function testAuthFilterWithArguments(): void
    {
        $filter = new AuthFilter();

        // 1. Web request unauthenticated
        $webRequest = $this->createRequest('http://localhost/dashboard');
        $response = $filter->before($webRequest, ['session']);
        $this->assertNotNull($response);
        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);
        $this->assertSame(302, $response->getStatusCode());

        // 2. API request unauthenticated via Accept header
        $apiJsonRequest = $this->createRequest('http://localhost/dashboard', ['Accept' => 'application/json']);
        $jsonResponse = $filter->before($apiJsonRequest, ['token']);
        $this->assertNotNull($jsonResponse);
        $this->assertSame(401, $jsonResponse->getStatusCode());

        // 3. API request unauthenticated via /api URI path
        $apiPathRequest = $this->createRequest('http://localhost/api/v1/data');
        $pathResponse = $filter->before($apiPathRequest, ['token']);
        $this->assertNotNull($pathResponse);
        $this->assertSame(401, $pathResponse->getStatusCode());

        // 4. Authenticated user proceeds
        $userModel = new UserModel();
        $user = new User(['username' => 'filter_auth_' . bin2hex(random_bytes(4)), 'active' => 1]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;
        auth()->login($user);

        $allowedResponse = $filter->before($webRequest, ['session']);
        $this->assertNull($allowedResponse);

        // 5. After filter returns response
        $dummyResponse = new Response(new App());
        $afterResponse = $filter->after($webRequest, $dummyResponse);
        $this->assertSame($dummyResponse, $afterResponse);
    }

    public function testPermissionFilter(): void
    {
        $filter = new PermissionFilter();

        // 1. Unauthenticated web request
        $webRequest = $this->createRequest('http://localhost/admin/posts');
        $redirectResponse = $filter->before($webRequest, ['posts.create']);
        $this->assertNotNull($redirectResponse);
        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $redirectResponse);
        $this->assertSame(302, $redirectResponse->getStatusCode());

        // 2. Unauthenticated API request
        $apiJsonRequest = $this->createRequest('http://localhost/api/posts', ['Accept' => 'application/json']);
        $unauthApiResponse = $filter->before($apiJsonRequest, ['posts.create']);
        $this->assertNotNull($unauthApiResponse);
        $this->assertSame(401, $unauthApiResponse->getStatusCode());

        // 3. Authenticate regular user
        $userModel = new UserModel();
        $user = new User(['username' => 'perm_filter_' . bin2hex(random_bytes(4)), 'active' => 1]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;
        auth()->login($user);

        auth()->permissions()->create('posts.create');
        auth()->permissions()->create('posts.delete');

        // 4. Forbidden response (web: 403 string)
        $forbiddenWeb = $filter->before($webRequest, ['posts.delete']);
        $this->assertNotNull($forbiddenWeb);
        $this->assertSame(403, $forbiddenWeb->getStatusCode());

        // 5. Forbidden response (API: 403 JSON)
        $forbiddenApi = $filter->before($apiJsonRequest, ['posts.delete']);
        $this->assertNotNull($forbiddenApi);
        $this->assertSame(403, $forbiddenApi->getStatusCode());

        // 6. Grant permission -> allowed (returns null)
        auth()->user($user)->grant()->permission('posts.create');
        $allowed = $filter->before($webRequest, ['posts.create']);
        $this->assertNull($allowed);

        // 7. Empty arguments -> allowed
        $this->assertNull($filter->before($webRequest, []));

        // 8. Superadmin bypass
        $superModel = new UserModel();
        $superUser = new User(['username' => 'super_filter_' . bin2hex(random_bytes(4)), 'active' => 1]);
        $superId = $superModel->insert($superUser);
        $superUser->id = (int) $superId;

        auth()->roles()->save(new VimaRole(name: 'superadmin'));
        auth()->user($superUser)->grant()->role('superadmin');
        auth()->login($superUser);

        $superAllowed = $filter->before($webRequest, ['non_existent_perm']);
        $this->assertNull($superAllowed);
    }

    public function testRoleFilter(): void
    {
        $filter = new RoleFilter();

        // 1. Unauthenticated web request
        $webRequest = $this->createRequest('http://localhost/admin');
        $redirectResponse = $filter->before($webRequest, ['manager']);
        $this->assertNotNull($redirectResponse);
        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $redirectResponse);
        $this->assertSame(302, $redirectResponse->getStatusCode());

        // 2. Unauthenticated API request
        $apiJsonRequest = $this->createRequest('http://localhost/api/admin', ['Accept' => 'application/json']);
        $unauthApiResponse = $filter->before($apiJsonRequest, ['manager']);
        $this->assertNotNull($unauthApiResponse);
        $this->assertSame(401, $unauthApiResponse->getStatusCode());

        // 3. Authenticate regular user
        $userModel = new UserModel();
        $user = new User(['username' => 'role_filter_' . bin2hex(random_bytes(4)), 'active' => 1]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;
        auth()->login($user);

        auth()->roles()->save(new VimaRole(name: 'manager'));
        auth()->roles()->save(new VimaRole(name: 'editor'));

        // 4. Forbidden when user does not have role
        $forbiddenWeb = $filter->before($webRequest, ['manager']);
        $this->assertNotNull($forbiddenWeb);
        $this->assertSame(403, $forbiddenWeb->getStatusCode());

        $forbiddenApi = $filter->before($apiJsonRequest, ['manager']);
        $this->assertNotNull($forbiddenApi);
        $this->assertSame(403, $forbiddenApi->getStatusCode());

        // 5. Grant role -> allowed
        auth()->user($user)->grant()->role('manager');
        $allowed = $filter->before($webRequest, ['manager']);
        $this->assertNull($allowed);

        // 6. Multiple roles accepted
        $allowedMulti = $filter->before($webRequest, ['editor', 'manager']);
        $this->assertNull($allowedMulti);

        // 7. Empty arguments -> allowed
        $this->assertNull($filter->before($webRequest, []));

        // 8. Superadmin bypass
        $superModel = new UserModel();
        $superUser = new User(['username' => 'super_role_' . bin2hex(random_bytes(4)), 'active' => 1]);
        $superId = $superModel->insert($superUser);
        $superUser->id = (int) $superId;

        auth()->roles()->save(new VimaRole(name: 'superadmin'));
        auth()->user($superUser)->grant()->role('superadmin');
        auth()->login($superUser);

        $superAllowed = $filter->before($webRequest, ['custom_role']);
        $this->assertNull($superAllowed);
    }
}
