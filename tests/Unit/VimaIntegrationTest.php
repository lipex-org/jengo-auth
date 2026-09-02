<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Auth\Entities\User;
use Jengo\Auth\Models\UserModel;
use Tests\TestCase;
use Vima\Core\Vima;
use Vima\Core\VimaManager;

class VimaIntegrationTest extends TestCase
{
    public function testVimaCoreAndCodeIgniterIntegration(): void
    {
        $vimaManager = vima();
        $this->assertInstanceOf(VimaManager::class, $vimaManager);

        $authVima = auth()->vima();
        $this->assertInstanceOf(VimaManager::class, $authVima);

        $userModel = new UserModel();
        $user = new User(['username' => 'vimauser', 'active' => 1]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        // Verify Vima Core facade & fluent interface
        $this->assertNotNull(Vima::roles());
        $this->assertNotNull(Vima::permissions());
        $this->assertNotNull(Vima::policies());
        $this->assertNotNull(Vima::container());
    }
}
