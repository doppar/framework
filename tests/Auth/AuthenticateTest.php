<?php

namespace Phaseolies\Auth\Security {
    class TestSessionStore
    {
        private array $data = [];

        public int $regenerateCallCount = 0;

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

        public function regenerate(bool $deleteOldSession = true): void
        {
            $this->regenerateCallCount++;
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

    use Phaseolies\Auth\Authable;
    use Phaseolies\Auth\Security\Authenticate;
    use PHPUnit\Framework\TestCase;

    class FakeAuthableModel extends Authable
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
            private ?Authable $user = null,
        ) {
            parent::__construct($actorName, [
                'model'       => FakeAuthableModel::class,
                'session_key' => $actorName . '_session',
            ]);
        }

        protected function getModel(): Authable
        {
            return new FakeAuthableModel();
        }

        public function hasTwoFactorEnabled(Authable $user): bool
        {
            return false;
        }

        public function user(): ?Authable
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
            FakeAuthableModel::$resolvedUser = null;
        }

        public function testAuthableGetAuthKeyNameDefaultsToEmail()
        {
            $user = new FakeAuthableModel();

            $this->assertSame('email', $user->getAuthKeyName());
        }

        public function testLoginDoesNotStoreFullUserPayloadInSessionCache()
        {
            global $authenticateSessionStore;

            $user = new FakeAuthableModel();
            $user->id = 42;
            $user->updated_at = '2026-04-29 10:00:00';

            $auth = new SessionTrackingAuthenticate('admin');

            $this->assertTrue($auth->login($user));
            $this->assertFalse($authenticateSessionStore->has('cache_auth_admin'));
        }

        public function testUserResolvedFromSessionDoesNotCreateSessionUserCache()
        {
            global $authenticateSessionStore;

            $user = new FakeAuthableModel();
            $user->id = 42;
            $user->updated_at = '2026-04-29 10:00:00';

            FakeAuthableModel::$resolvedUser = $user;
            $authenticateSessionStore->put('admin_session', 42);

            $auth = new SessionTrackingAuthenticate('admin');

            $this->assertSame($user, $auth->user());
            $this->assertFalse($authenticateSessionStore->has('cache_auth_admin'));
        }

        public function testLoginRegeneratesSessionIdToPreventFixation()
        {
            global $authenticateSessionStore;

            $user = new FakeAuthableModel();
            $user->id = 42;

            $auth = new SessionTrackingAuthenticate('admin');

            $this->assertTrue($auth->login($user));
            $this->assertSame(1, $authenticateSessionStore->regenerateCallCount);
        }

        public function testCompleteTwoFactorLoginRegeneratesSessionId()
        {
            global $authenticateSessionStore;

            $user = new FakeAuthableModel();
            $user->id = 7;
            FakeAuthableModel::$resolvedUser = $user;

            $authenticateSessionStore->put('2fa_admin_user_id', 7);
            $authenticateSessionStore->put('2fa_admin_remember', false);

            $auth = new SessionTrackingAuthenticate('admin');

            $this->assertTrue($auth->completeTwoFactorLogin());
            $this->assertSame(1, $authenticateSessionStore->regenerateCallCount);
        }

        public function testLoginAcceptsAuthable()
        {
            $user = new FakeAuthableModel();
            $user->id = 99;

            $auth = new SessionTrackingAuthenticate('admin');

            $this->assertTrue($auth->login($user));
            $this->assertSame(99, $auth->id());
            $this->assertInstanceOf(Authable::class, $auth->user());
        }
    }
}
