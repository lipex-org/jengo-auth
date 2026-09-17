<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Jengo\Auth\Testing\AuthenticationAssertions;

abstract class TestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use AuthenticationAssertions;

    protected $migrate = false;
    protected $refresh = true;
    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Reset Auth and Vima services
        Services::reset(true);

        $runner = Services::migrations();
        $runner->setNamespace('Vima\CodeIgniter')->latest();
        $runner->setNamespace('Jengo\Auth')->latest();

        // Initialize Vima service for tests
        Services::vima();

        // Stub email service to avoid shell execution of sendmail during tests
        $emailStub = $this->createStub(\CodeIgniter\Email\Email::class);
        $emailStub->method('send')->willReturn(true);
        Services::injectMock('email', $emailStub);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
