<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Events\Events;
use Config\Services;
use Jengo\Auth\DTOs\AuthResponseData;
use Jengo\Auth\Modifiers\JsonModifier;
use Jengo\Auth\Modifiers\StandardViewModifier;
use Jengo\Auth\Support\ResponseHandler;
use Jengo\Base\Validation\FormFailedResponseHolder;
use Tests\TestCase;

class ResponseHandlerTest extends TestCase
{
    public function testRendersViaConfiguredModifier(): void
    {
        $handler = new ResponseHandler();
        $this->assertInstanceOf(StandardViewModifier::class, $handler->getModifier());

        // Swap to JSON modifier
        $handler->setModifier(new JsonModifier());
        $this->assertInstanceOf(JsonModifier::class, $handler->getModifier());

        $data = new AuthResponseData(
            action: 'login.view',
            status: 'success',
            statusCode: 200,
            message: 'OK'
        );

        $response = $handler->render('login.view', $data);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', (string) $response->getHeaderLine('Content-Type'));
    }

    public function testNotFoundAndValidationFailedHelpers(): void
    {
        $handler = new ResponseHandler(new JsonModifier());

        $notFound = $handler->notFound('test.not_found');
        $this->assertSame(404, $notFound->getStatusCode());

        $valFailed = $handler->validationFailed('test.failed', ['email' => 'Invalid email']);
        $this->assertSame(422, $valFailed->getStatusCode());
        $body = json_decode((string) $valFailed->getBody(), true);
        $this->assertArrayHasKey('errors', $body);
        $this->assertSame('Invalid email', $body['errors']['email']);
    }

    public function testHandleFormFailedEventIntegration(): void
    {
        $handler = new ResponseHandler(new JsonModifier());
        $request = Services::request();
        $holder = new FormFailedResponseHolder(['password' => 'Password too short'], $request);

        $handler->handleFormFailed($holder, 'register.validation_failed');

        $response = $holder->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(422, $response->getStatusCode());
    }
}
