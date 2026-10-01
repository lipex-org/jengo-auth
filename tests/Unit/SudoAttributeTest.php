<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\Services;
use Jengo\Auth\Attributes\Sudo;
use Jengo\Auth\Entities\User;
use Jengo\Auth\Filters\SudoFilter;

class SudoTestController
{
    #[Sudo(lifetime: '1 hour')]
    public function deleteAccount()
    {
        return 'account_deleted';
    }

    #[Sudo(forceFresh: true)]
    public function revealSecrets()
    {
        return 'secrets_revealed';
    }
}

final class SudoAttributeTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Services::session()->destroy();
        Services::sudo()->deactivate();
    }

    private function createRequest(array $headers = []): IncomingRequest
    {
        $config = new App();
        $uri = new URI('http://example.com/test');
        $userAgent = new UserAgent();
        $request = new IncomingRequest($config, $uri, 'php://input', $userAgent);

        foreach ($headers as $key => $val) {
            $request->setHeader($key, $val);
        }

        return $request;
    }

    public function testSudoFilterBlocksUnauthenticatedUser(): void
    {
        $request = $this->createRequest(['Accept' => 'application/json']);

        $router = Services::router();
        $ref = new \ReflectionClass($router);
        $propCtrl = $ref->getProperty('controller');
        $propCtrl->setAccessible(true);
        $propCtrl->setValue($router, SudoTestController::class);

        $propMethod = $ref->getProperty('method');
        $propMethod->setAccessible(true);
        $propMethod->setValue($router, 'deleteAccount');

        $filter = new SudoFilter();
        $response = $filter->before($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testSudoFilterPromptsWhenSudoInactiveJson(): void
    {
        $user = new User([
            'id'            => 99,
            'email'         => 'alice@example.com',
            'password_hash' => password_hash('pass', PASSWORD_BCRYPT),
        ]);
        Services::auth()->setUser($user);

        $request = $this->createRequest(['Accept' => 'application/json']);

        $router = Services::router();
        $ref = new \ReflectionClass($router);
        $propCtrl = $ref->getProperty('controller');
        $propCtrl->setAccessible(true);
        $propCtrl->setValue($router, SudoTestController::class);

        $propMethod = $ref->getProperty('method');
        $propMethod->setAccessible(true);
        $propMethod->setValue($router, 'deleteAccount');

        $filter = new SudoFilter();
        $response = $filter->before($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('true', $response->getHeaderLine('X-Jengo-Sudo-Required'));

        $body = json_decode($response->getBody(), true);
        $this->assertTrue($body['sudo_required']);
        $this->assertNotEmpty($body['available_factors']);
    }

    public function testSudoFilterPassesWhenSudoActive(): void
    {
        $user = new User([
            'id'            => 99,
            'email'         => 'alice@example.com',
            'password_hash' => password_hash('pass', PASSWORD_BCRYPT),
        ]);
        Services::auth()->setUser($user);

        // Activate sudo
        Services::sudo()->activate(3600, 'password');

        $request = $this->createRequest(['Accept' => 'application/json']);

        $router = Services::router();
        $ref = new \ReflectionClass($router);
        $propCtrl = $ref->getProperty('controller');
        $propCtrl->setAccessible(true);
        $propCtrl->setValue($router, SudoTestController::class);

        $propMethod = $ref->getProperty('method');
        $propMethod->setAccessible(true);
        $propMethod->setValue($router, 'deleteAccount');

        $filter = new SudoFilter();
        $response = $filter->before($request);

        // Active sudo passes with null
        $this->assertNull($response);
    }
}
