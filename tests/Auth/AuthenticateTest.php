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
                global $authenticateRememberCookies;

                return isset($authenticateRememberCookies[$key]);
            }

            public function get(string $key): mixed
            {
                global $authenticateRememberCookies;

                return $authenticateRememberCookies[$key] ?? null;
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
    use Phaseolies\DI\Container;
    use PHPUnit\Framework\TestCase;
    use Tests\Support\MockContainer;

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

    class RememberedFakeUser extends FakeAuthableModel
    {
        public ?string $fakeRememberToken = null;
        public ?string $fakeTwoFactorSecret = null;

        public function getRememberToken(): ?string
        {
            return $this->fakeRememberToken;
        }

        public function getTwoFactorSecret(): ?string
        {
            return $this->fakeTwoFactorSecret;
        }
    }

    class RememberCookieAuthenticate extends Authenticate
    {
        public function __construct()
        {
            parent::__construct('web', [
                'model'       => RememberedFakeUser::class,
                'session_key' => 'web_session',
            ]);
        }

        protected function getModel(): Authable
        {
            return new RememberedFakeUser();
        }

        protected function getRememberCookieName(): string
        {
            return 'remember_test';
        }
    }

    class AuthenticateTest extends TestCase
    {
        protected function setUp(): void
        {
            global $authenticateSessionStore, $authenticateRememberCookies;

            $authenticateSessionStore = new \Phaseolies\Auth\Security\TestSessionStore();
            $authenticateRememberCookies = [];
            FakeAuthableModel::$resolvedUser = null;
        }

        /**
         * Sign a browser in with a valid remember cookie for a user.
         */
        private function rememberUser(?string $twoFactorSecret): RememberedFakeUser
        {
            global $authenticateRememberCookies;

            $container = new MockContainer();
            Container::setInstance($container);
            $container->bind('hash', fn() => new class {
                public function check(?string $value, ?string $hash): bool
                {
                    return password_verify((string) $value, (string) $hash);
                }
            });
            $container->bind('crypt', fn() => new class {
                public function decrypt(string $value): string
                {
                    return $value;
                }
            });

            $token = 'remember-token';

            $user = new RememberedFakeUser();
            $user->id = 7;
            $user->fakeRememberToken = password_hash($token, PASSWORD_BCRYPT, ['cost' => 4]);
            $user->fakeTwoFactorSecret = $twoFactorSecret;
            FakeAuthableModel::$resolvedUser = $user;

            $authenticateRememberCookies['remember_test'] =
                '7|' . $token . '|' . password_hash('7' . $token, PASSWORD_BCRYPT, ['cost' => 4]);

            return $user;
        }

        public function testRememberCookieLogsInUserWithoutTwoFactor()
        {
            global $authenticateSessionStore;

            $user = $this->rememberUser(null);

            $this->assertSame($user, (new RememberCookieAuthenticate())->user());
            $this->assertSame(7, $authenticateSessionStore->get('web_session'));
        }

        public function testRememberCookieDoesNotBypassTwoFactor()
        {
            global $authenticateSessionStore;

            $this->rememberUser('encrypted-secret');

            $this->assertNull((new RememberCookieAuthenticate())->user());
            $this->assertFalse($authenticateSessionStore->has('web_session'));
            $this->assertSame(7, $authenticateSessionStore->get('2fa_web_user_id'));
            $this->assertTrue($authenticateSessionStore->get('2fa_web_remember'));
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
