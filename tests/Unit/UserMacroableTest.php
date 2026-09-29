<?php

declare(strict_types=1);

namespace Tests\Unit;

use BadMethodCallException;
use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Auth\Entities\User;

class UserMixinExample
{
    public function getDisplayName(): \Closure
    {
        return function (string $prefix = '') {
            /** @var User $this */
            $name = $this->username ?? 'Anonymous';
            return $prefix !== '' ? "{$prefix} {$name}" : $name;
        };
    }
}

class UserMacroableTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        User::flushMacros();
        parent::tearDown();
    }

    public function testUserCanRegisterAndExecuteInstanceMacro(): void
    {
        User::macro('hasCompletedProfile', function (): bool {
            /** @var User $this */
            return !empty($this->username) && (bool) ($this->active ?? false);
        });

        $user = new User([
            'id'       => 10,
            'username' => 'ian_oching',
            'active'   => 1,
        ]);

        $this->assertTrue($user->hasCompletedProfile());

        $incompleteUser = new User([
            'id'       => 11,
            'username' => '',
            'active'   => 1,
        ]);

        $this->assertFalse($incompleteUser->hasCompletedProfile());
    }

    public function testUserCanRegisterAndExecuteStaticMacro(): void
    {
        User::macro('guest', function (): User {
            return new User([
                'id'       => 0,
                'username' => 'guest',
                'active'   => 0,
            ]);
        });

        $guest = User::guest();

        $this->assertInstanceOf(User::class, $guest);
        $this->assertSame('guest', $guest->username);
        $this->assertSame(0, $guest->id);
    }

    public function testUserCanRegisterMixin(): void
    {
        User::mixin(new UserMixinExample());

        $user = new User([
            'id'       => 42,
            'username' => 'Commander',
        ]);

        $this->assertSame('Commander', $user->getDisplayName());
        $this->assertSame('Sir Commander', $user->getDisplayName('Sir'));
    }

    public function testThrowsExceptionOnUndefinedUserMacro(): void
    {
        $this->expectException(BadMethodCallException::class);

        $user = new User();
        $user->nonExistentMethod();
    }
}
