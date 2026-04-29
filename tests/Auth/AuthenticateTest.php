<?php

namespace Phaseolies\Auth\Security {
    class TestSessionStore
    {
        private array $data = [];

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->data[$key] ?? $default;
        }

        public function put(string $key, mixed $value): void
        {
            $this->data[$key] = $value;
        }

        public function has(string $key): bool
        {
            return array_key_exists($key, $this->data);
        }

        public function forget(string $key): void
        {
            unset($this->data[$key]);
        }
    }

    function session(?string $key = null, mixed $default = null): mixed
    {
        global $authenticateSessionStore;

        $authenticateSessionStore ??= new TestSessionStore();

        if ($key === null) {
            return $authenticateSessionStore;
        }

        return $authenticateSessionStore->get($key, $default);
    }

    function now(): object
    {
        return new class {
            public function addMinutes(int $minutes): object
            {
                return new class {
                    public int $timestamp = 1_700_000_000;
                };
            }
        };
    }

    function cookie(): object
    {
        return new class {
            public function has(string $key): bool
            {
                return false;
            }
        };
    }

    function request(): object
    {
        return new class {
            public function isApiRequest(): bool
            {
                return false;
            }
        };
    }
}

namespace Tests\Unit\Auth {

    use Phaseolies\Auth\ActorManager;
    use Phaseolies\Auth\Security\Authenticate;
    use Phaseolies\Database\Entity\Model;
    use PHPUnit\Framework\TestCase;

    class FakeAuthenticatableModel extends Model
    {
        public static ?self $resolvedUser = null;

        public static function find(string|int|array $primaryKey)
        {
            if (is_array($primaryKey)) {
                return null;
            }

            return static::$resolvedUser && (string) static::$resolvedUser->id === (string) $primaryKey
                ? static::$resolvedUser
                : null;
        }
    }

    class SessionTrackingAuthenticate extends Authenticate
    {
        public function __construct(
            string $actorName,
            private ?Model $user = null,
        ) {
            parent::__construct($actorName, [
                'model'       => FakeAuthenticatableModel::class,
                'session_key' => $actorName . '_session',
            ]);
        }

        protected function getModel(): Model
        {
            return new FakeAuthenticatableModel();
        }

        public function hasTwoFactorEnabled(Model $user): bool
        {
            return false;
        }

        public function user(): ?Model
        {
            return $this->user ?? parent::user();
        }
    }

    class AuthenticateTest extends TestCase
    {
        protected function setUp(): void
        {
            global $authenticateSessionStore;

            $authenticateSessionStore = new \Phaseolies\Auth\Security\TestSessionStore();
            FakeAuthenticatableModel::$resolvedUser = null;
        }

        public function testLoginDoesNotStoreFullUserPayloadInSessionCache()
        {
            global $authenticateSessionStore;

            $user = new FakeAuthenticatableModel();
            $user->id = 42;
            $user->updated_at = '2026-04-29 10:00:00';

            $auth = new SessionTrackingAuthenticate('admin');

            $this->assertTrue($auth->login($user));
            $this->assertFalse($authenticateSessionStore->has('cache_auth_admin'));
        }

        public function testUserResolvedFromSessionDoesNotCreateSessionUserCache()
        {
            global $authenticateSessionStore;

            $user = new FakeAuthenticatableModel();
            $user->id = 42;
            $user->updated_at = '2026-04-29 10:00:00';

            FakeAuthenticatableModel::$resolvedUser = $user;
            $authenticateSessionStore->put('admin_session', 42);

            $auth = new SessionTrackingAuthenticate('admin');

            $this->assertSame($user, $auth->user());
            $this->assertFalse($authenticateSessionStore->has('cache_auth_admin'));
        }
    }
}
