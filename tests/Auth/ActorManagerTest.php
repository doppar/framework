<?php

namespace Phaseolies\Auth {
    function config(string $key, mixed $default = null): mixed
    {
        $map = [
            'auth.default'          => 'web',
            'auth.actors.web'       => [
                'model'       => 'App\Models\User',
                'session_key' => 'auth_web_user_id',
            ],
            'auth.actors.admin'     => [
                'model'       => 'App\Models\Admin',
                'session_key' => 'auth_admin_user_id',
            ],
        ];

        if (array_key_exists($key, $map)) {
            return $map[$key];
        }

        foreach ($map as $mapKey => $value) {
            if ($mapKey === $key) {
                return $value;
            }
        }

        return $default;
    }
}

namespace Tests\Unit\Auth {

    use Phaseolies\Auth\ActorManager;
    use Phaseolies\Auth\Security\Authenticate;
    use PHPUnit\Framework\TestCase;
    use InvalidArgumentException;

    class ActorManagerTest extends TestCase
    {
        private ActorManager $manager;

        protected function setUp(): void
        {
            $this->manager = new ActorManager();
        }

        public function testActorReturnsAuthenticateInstance()
        {
            $actor = $this->manager->actor('web');

            $this->assertInstanceOf(Authenticate::class, $actor);
        }

        public function testActorReturnsSameInstanceOnSecondCall()
        {
            $actor1 = $this->manager->actor('web');
            $actor2 = $this->manager->actor('web');

            $this->assertSame($actor1, $actor2);
        }

        public function testActorResolvesWithNameProvided()
        {
            $actor = $this->manager->actor('admin');

            $this->assertInstanceOf(Authenticate::class, $actor);
        }

        public function testActorThrowsForUndefinedActorName()
        {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessageMatches('/ghost/');

            $this->manager->actor('ghost');
        }

        public function testForgetActorsClearsResolvedInstances()
        {
            $actor1 = $this->manager->actor('web');
            $this->manager->forgetActors();
            $actor2 = $this->manager->actor('web');

            $this->assertNotSame($actor1, $actor2);
        }

        public function testForgetActorsClearsAllActors()
        {
            $this->manager->actor('web');
            $this->manager->actor('admin');

            $this->manager->forgetActors();

            $webAfter   = $this->manager->actor('web');
            $adminAfter = $this->manager->actor('admin');

            $this->assertInstanceOf(Authenticate::class, $webAfter);
            $this->assertInstanceOf(Authenticate::class, $adminAfter);
        }

        public function testActorNameIsSetCorrectly()
        {
            $actor = $this->manager->actor('web');

            $this->assertEquals('web', $actor->name());
        }

        public function testActorNameIsSetForAdmin()
        {
            $actor = $this->manager->actor('admin');

            $this->assertEquals('admin', $actor->name());
        }

        public function testDifferentActorNamesReturnDifferentInstances()
        {
            $web   = $this->manager->actor('web');
            $admin = $this->manager->actor('admin');

            $this->assertNotSame($web, $admin);
        }

        public function testActorDefaultsToConfiguredDefaultWhenNameIsNull()
        {
            $default = $this->manager->actor();

            $this->assertInstanceOf(Authenticate::class, $default);
            $this->assertSame('web', $default->name());
        }

        public function testMagicCallProxiesToDefaultActor()
        {
            $this->assertSame('web', $this->manager->name());
        }
    }
}
