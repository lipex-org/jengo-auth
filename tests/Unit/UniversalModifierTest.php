<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use Config\App;
use Config\Services;
use Jengo\Auth\DTOs\AuthResponseData;
use Jengo\Auth\Modifiers\InertiaModifier;
use Jengo\Auth\Modifiers\JsonModifier;
use Jengo\Auth\Modifiers\StandardViewModifier;
use Jengo\Auth\Modifiers\UniversalModifier;
use Tests\TestCase;

class UniversalModifierTest extends TestCase
{
    protected function createMockRequest(array $headers = [], bool $isAjax = false): IncomingRequest
    {
        $config = new App();
        $uri = new URI('http://example.com/login');
        $userAgent = new UserAgent();

        $request = new IncomingRequest($config, $uri, 'php://input', $userAgent);
        foreach ($headers as $name => $value) {
            $request->setHeader($name, $value);
        }

        return $request;
    }

    public function testResolvesInertiaModifierWhenXInertiaHeaderPresent(): void
    {
        $modifier = new UniversalModifier();
        $request = $this->createMockRequest(['X-Inertia' => 'true']);

        $resolved = $modifier->resolveModifier($request);
        $this->assertInstanceOf(InertiaModifier::class, $resolved);
    }

    public function testResolvesJsonModifierWhenAcceptOrContentTypeIsJson(): void
    {
        $modifier = new UniversalModifier();

        $reqAcceptJson = $this->createMockRequest(['Accept' => 'application/json']);
        $this->assertInstanceOf(JsonModifier::class, $modifier->resolveModifier($reqAcceptJson));

        $reqContentTypeJson = $this->createMockRequest(['Content-Type' => 'application/json']);
        $this->assertInstanceOf(JsonModifier::class, $modifier->resolveModifier($reqContentTypeJson));
    }

    public function testResolvesStandardViewModifierByDefault(): void
    {
        $modifier = new UniversalModifier();
        $request = $this->createMockRequest(['Accept' => 'text/html,application/xhtml+xml']);

        $resolved = $modifier->resolveModifier($request);
        $this->assertInstanceOf(StandardViewModifier::class, $resolved);
    }

    public function testResolvesInertiaModifierForBrowserRequestsWhenViewRendererIsInertia(): void
    {
        $modifier = new UniversalModifier(viewRenderer: 'inertia');
        $request = $this->createMockRequest(['Accept' => 'text/html,application/xhtml+xml']);

        $resolved = $modifier->resolveModifier($request);
        $this->assertInstanceOf(InertiaModifier::class, $resolved);
    }

    public function testJsonRequestsStillWinEvenWhenViewRendererIsInertia(): void
    {
        $modifier = new UniversalModifier(viewRenderer: 'inertia');
        $request = $this->createMockRequest(['Accept' => 'application/json']);

        $resolved = $modifier->resolveModifier($request);
        $this->assertInstanceOf(JsonModifier::class, $resolved);
    }

    public function testAllowsCustomConfiguredModifierClasses(): void
    {
        $modifier = new UniversalModifier(
            inertiaModifier: InertiaModifier::class,
            jsonModifier: JsonModifier::class,
            standardModifier: StandardViewModifier::class
        );

        $this->assertInstanceOf(InertiaModifier::class, $modifier->getInertiaModifier());
        $this->assertInstanceOf(JsonModifier::class, $modifier->getJsonModifier());
        $this->assertInstanceOf(StandardViewModifier::class, $modifier->getStandardModifier());
    }

    public function testAllowsCustomConfiguredModifierInstances(): void
    {
        $customStandard = new StandardViewModifier();
        $customJson = new JsonModifier();
        $customInertia = new InertiaModifier();

        $modifier = new UniversalModifier(
            inertiaModifier: $customInertia,
            jsonModifier: $customJson,
            standardModifier: $customStandard
        );

        $this->assertSame($customInertia, $modifier->getInertiaModifier());
        $this->assertSame($customJson, $modifier->getJsonModifier());
        $this->assertSame($customStandard, $modifier->getStandardModifier());
    }

    public function testDelegatesModifyValidationFailed(): void
    {
        $modifier = new UniversalModifier();
        $request = $this->createMockRequest(['Accept' => 'application/json']);

        $response = $modifier->modifyValidationFailed(['email' => 'Invalid email'], $request);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('The given data was invalid', (string) $response->getBody());
    }

    public function testDelegatesModifyToResolvedModifier(): void
    {
        $modifier = new UniversalModifier();
        $request = $this->createMockRequest(['Accept' => 'application/json']);

        $data = new AuthResponseData(
            action: 'login.success',
            status: 'success',
            statusCode: 200,
            message: 'Authenticated successfully'
        );

        $response = $modifier->modify('login.success', $data, $request);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Authenticated successfully', (string) $response->getBody());
    }
}
