<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Auth\Entities\User;
use Jengo\Auth\Models\UserModel;
use Tests\TestCase;
use Vima\Core\Audit\Contracts\AuditRepositoryInterface;
use Vima\Core\Audit\Entities\BareAuditLog;
use Vima\Core\Audit\Services\AuditService;
use Vima\Core\Config\VimaConfig;
use Vima\Core\Events\Access\AuthorizationChecked;
use Vima\Core\User\Services\UserResolutionService;

class AuditTest extends TestCase
{
    public function testAuditLoggingOnAuthEvents(): void
    {
        $userModel = new UserModel();
        $user = new User(['username' => 'audituser', 'active' => 1]);
        $id = $userModel->insert($user);
        $user->id = (int) $id;

        $logged = [];
        $repoMock = new class($logged) implements AuditRepositoryInterface {
            public function __construct(private array &$logged) {}
            public function log(BareAuditLog|array $data): void {
                $this->logged[] = is_array($data) ? $data : (array) $data;
            }
            public function getRecent(int $limit = 50): array {
                return $this->logged;
            }
        };

        $config = new VimaConfig();
        $auditService = new AuditService($repoMock, $config, new UserResolutionService($config));

        $event = new AuthorizationChecked(
            permission: 'posts.delete',
            user: $user,
            result: false,
            reason: 'Explicit deny'
        );

        $auditService->handleAuthorizationChecked($event);

        $this->assertCount(1, $logged);
        $this->assertSame('posts.delete', $logged[0]['permission']);
        $this->assertSame(0, $logged[0]['result']);
    }
}
