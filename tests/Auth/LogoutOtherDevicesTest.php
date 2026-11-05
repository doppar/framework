<?php

namespace Tests\Unit\Auth;

use PDO;
use PHPUnit\Framework\TestCase;
use Phaseolies\Auth\ActorManager;
use Phaseolies\Auth\Authable;
use Phaseolies\Auth\Security\Authenticate;
use Phaseolies\Auth\Security\PasswordHashing;
use Phaseolies\Config\Config;
use Phaseolies\Database\Database;
use Phaseolies\DI\Container;
use Phaseolies\Http\HeaderBag;
use Phaseolies\Http\InputBag;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Phaseolies\Http\ServerBag;
use Phaseolies\Middleware\AuthenticateSession;
use Phaseolies\Session\Contracts\SessionStoreInterface;
use Phaseolies\Session\RotationPointer;
use Phaseolies\Session\SessionManager;
use Phaseolies\Session\Stores\ArrayStore;
use Phaseolies\Session\Stores\DatabaseStore;
use Phaseolies\Support\CookieJar;
use Phaseolies\Support\Encryption;
use Phaseolies\Support\Facades\Facade;
use Phaseolies\Support\Session;
use Tests\Support\MockContainer;

class DeviceUser extends Authable
{
    protected $table = 'users';

    protected $timeStamps = false;

    protected $creatable = ['email', 'password', 'remember_token'];
}

/**
 * An actor that records the remember cookies it sends instead of calling setcookie().
 */
class CookieRecordingAuthenticate extends Authenticate
{
    /**
     * @var array<string, string>
     */
    public static array $sent = [];

    protected function setRememberCookie(string $value): void
    {
        self::$sent[$this->getRememberCookieName()] = \Phaseolies\Support\Facades\Crypt::encrypt($value);
    }
}

class CookieRecordingActorManager extends ActorManager
{
    protected function resolve(string $name): Authenticate
    {
        return new CookieRecordingAuthenticate($name, config("auth.actors.{$name}"));
    }
}

/**
 * A model whose $creatable list leaves the credential columns out.
 */
class LockedDownUser extends Authable
{
    protected $table = 'users';

    protected $timeStamps = false;

    protected $creatable = ['email'];
}

/**
 * A model that reads its password from another column, but only overrides getAuthPassword().
 */
class RenamedPasswordUser extends Authable
{
    protected $table = 'users';

    protected $timeStamps = false;

    public function getAuthPassword(): ?string
    {
        return $this->passwd;
    }
}

/**
 * Signs one user in on several devices, each a separate request with its own
 * session cookie, and checks what logoutOtherDevices() leaves signed in.
 */
class LogoutOtherDevicesTest extends TestCase
{
    private const CONNECTION = 'logout_devices_test';

    private const COOKIE = 'test_session';

    private const PASSWORD = 'correct horse battery staple';

    private string $database;

    private SessionStoreInterface $store;

    private array $sessionConfig = [
        'cookie' => self::COOKIE,
        'lifetime' => 120,
        'stateless' => [],
        'lottery' => [0, 100],
        'secure' => false,
    ];

    protected function setUp(): void
    {
        $this->database = sys_get_temp_dir() . '/doppar_devices_' . bin2hex(random_bytes(6)) . '.sqlite';

        $container = new MockContainer();
        Container::setInstance($container);
        $this->resetConfig();

        Config::set('app.key', 'base64:ImGTGoQ6ZhM7yBlMvp41ejp0nt8juImy1aIbf6shQCI=');
        Config::set('database.connections.' . self::CONNECTION, ['driver' => 'sqlite', 'database' => $this->database, 'prefix' => '']);
        Config::set('database.default', self::CONNECTION);
        Config::set('hashing', ['driver' => 'bcrypt', 'bcrypt' => ['rounds' => 4]]);
        Config::set('auth', [
            'default' => 'web',
            'actors' => [
                'web' => ['model' => DeviceUser::class, 'session_key' => 'user_id'],
                'admin' => ['model' => DeviceUser::class, 'session_key' => 'admin_id'],
            ],
        ]);

        $container->bind('db', fn () => new Database());
        $container->singleton('hash', PasswordHashing::class);
        $container->singleton('crypt', Encryption::class);
        $container->singleton('cookie', CookieJar::class);
        $container->singleton('auth', CookieRecordingActorManager::class);
        CookieRecordingAuthenticate::$sent = [];

        if (method_exists(Facade::class, 'clearResolvedInstances')) {
            Facade::clearResolvedInstances();
        }

        $pdo = Database::getPdoInstance(self::CONNECTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, password TEXT, remember_token TEXT NULL, two_factor_secret TEXT NULL)');
        $pdo->exec(
            'CREATE TABLE sessions (id VARCHAR(128) PRIMARY KEY, user_id INTEGER NULL, ip_address VARCHAR(45) NULL, '
                . 'user_agent TEXT NULL, payload TEXT NOT NULL, last_activity INTEGER NOT NULL)'
        );
        $pdo->prepare('INSERT INTO users (id, email, password) VALUES (?, ?, ?)')
            ->execute([1, 'ada@example.com', password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4])]);

        $this->useStore($this->databaseStore());

        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];

        SessionManager::useStore(null);

        foreach (['connections', 'drivers', 'transactions'] as $property) {
            (new \ReflectionClass(Database::class))->getProperty($property)->setValue(null, []);
        }

        $this->resetConfig();
        (new \ReflectionClass(Container::class))->getProperty('instance')->setValue(null, null);

        @unlink($this->database);
    }

    public function test_it_signs_out_every_other_device_and_keeps_this_one(): void
    {
        $laptop = $this->signIn('Laptop');
        $phone = $this->signIn('Phone');
        $tablet = $this->signIn('Tablet');

        [$laptop, $result] = $this->onDevice($laptop, fn (Authenticate $auth) => $auth->logoutOtherDevices(self::PASSWORD));

        $this->assertTrue($result);
        $this->assertTrue($this->isSignedIn($laptop));
        $this->assertFalse($this->isSignedIn($phone));
        $this->assertFalse($this->isSignedIn($tablet));
        $this->assertSame(1, $this->sessionRows(), 'Only the current device keeps a stored session.');
    }

    public function test_the_password_still_works_after_it_is_rehashed(): void
    {
        $laptop = $this->signIn('Laptop');
        $before = $this->storedPassword();

        $this->onDevice($laptop, fn (Authenticate $auth) => $auth->logoutOtherDevices(self::PASSWORD));

        $this->assertNotSame($before, $this->storedPassword());
        $this->assertTrue(password_verify(self::PASSWORD, $this->storedPassword()));
    }

    public function test_a_wrong_password_changes_nothing(): void
    {
        $laptop = $this->signIn('Laptop');
        $phone = $this->signIn('Phone');
        $before = $this->storedPassword();

        [, $result] = $this->onDevice($laptop, fn (Authenticate $auth) => $auth->logoutOtherDevices('wrong password'));

        $this->assertFalse($result);
        $this->assertSame($before, $this->storedPassword());
        $this->assertTrue($this->isSignedIn($phone));
    }

    public function test_it_does_nothing_for_a_guest(): void
    {
        [, $result] = $this->onDevice(null, fn (Authenticate $auth) => $auth->logoutOtherDevices(self::PASSWORD));

        $this->assertFalse($result);
    }

    public function test_with_a_store_that_does_not_track_users_the_fingerprint_signs_other_devices_out(): void
    {
        $this->useStore(new ArrayStore(7200));

        $laptop = $this->signIn('Laptop');
        $phone = $this->signIn('Phone');

        $this->onDevice($laptop, fn (Authenticate $auth) => $auth->logoutOtherDevices(self::PASSWORD));

        // The phone's session still exists, but was signed in with the old password.
        [, $phoneCurrent] = $this->onDevice($phone, fn (Authenticate $auth) => $auth->isSessionCurrent());
        [, $laptopCurrent] = $this->onDevice($laptop, fn (Authenticate $auth) => $auth->isSessionCurrent());

        $this->assertFalse($phoneCurrent);
        $this->assertTrue($laptopCurrent);
        $this->assertFalse($this->isSignedIn($phone), 'A stale session stays signed out.');
        $this->assertTrue($this->isSignedIn($laptop));
    }

    public function test_the_middleware_signs_a_stale_session_out_before_the_request_runs(): void
    {
        $this->useStore(new ArrayStore(7200));

        $laptop = $this->signIn('Laptop');
        $phone = $this->signIn('Phone');
        $this->onDevice($laptop, fn (Authenticate $auth) => $auth->logoutOtherDevices(self::PASSWORD));

        [, $seenByRoute] = $this->onDevice($phone, function (Authenticate $auth, Request $request) {
            $seen = null;

            (new AuthenticateSession())($request, function () use (&$seen): Response {
                $seen = app('auth')->check();

                return new Response();
            });

            return $seen;
        });

        $this->assertFalse($seenByRoute);
    }

    public function test_changing_the_password_during_a_request_keeps_that_device_signed_in(): void
    {
        $this->useStore(new ArrayStore(7200));

        $laptop = $this->signIn('Laptop');
        $phone = $this->signIn('Phone');

        $this->onDevice($laptop, function (Authenticate $auth, Request $request): void {
            (new AuthenticateSession())($request, function (): Response {
                $user = app('auth')->user();
                $user->password = password_hash('a new password', PASSWORD_BCRYPT, ['cost' => 4]);
                $user->save();

                return new Response();
            });
        });

        $this->assertTrue($this->isCurrentAndSignedIn($laptop));
        $this->assertFalse($this->isCurrentAndSignedIn($phone));
    }

    public function test_a_session_from_before_fingerprints_does_not_survive_signing_others_out(): void
    {
        $this->useStore(new ArrayStore(7200));

        $laptop = $this->signIn('Laptop');
        $phone = $this->signIn('Phone');
        $this->onDevice($phone, fn (Authenticate $auth, Request $request) => $request->session()->forget('password_hash_web'));

        $this->onDevice($laptop, fn (Authenticate $auth) => $auth->logoutOtherDevices(self::PASSWORD));

        [, $current] = $this->onDevice($phone, fn (Authenticate $auth) => $auth->isSessionCurrent());

        $this->assertFalse($current);
        $this->assertFalse($this->isSignedIn($phone));
    }

    public function test_a_session_from_before_fingerprints_with_a_valid_remember_cookie_signs_back_in(): void
    {
        $this->useStore(new ArrayStore(7200));

        [$laptop, $remember] = $this->signInRemembered('Laptop');
        $this->onDevice($laptop, fn (Authenticate $auth, Request $request) => $request->session()->forget('password_hash_web'), $remember);

        [$laptop, $current] = $this->onDevice($laptop, fn (Authenticate $auth) => $auth->isSessionCurrent(), $remember);
        [, $fingerprinted] = $this->onDevice($laptop, fn (Authenticate $auth, Request $request) => $request->session()->has('password_hash_web'), $remember);

        $this->assertTrue($current);
        $this->assertTrue($fingerprinted);
        $this->assertNotNull($this->storedRememberToken(), 'The remember token was left alone.');
    }

    public function test_a_session_from_before_fingerprints_with_a_replaced_remember_cookie_is_signed_out(): void
    {
        $this->useStore(new ArrayStore(7200));

        $laptop = $this->signIn('Laptop');
        [$phone, $phoneRemember] = $this->signInRemembered('Phone');
        $this->onDevice($phone, fn (Authenticate $auth, Request $request) => $request->session()->forget('password_hash_web'), $phoneRemember);

        $this->onDevice($laptop, fn (Authenticate $auth) => $auth->logoutOtherDevices(self::PASSWORD));

        [, $current] = $this->onDevice($phone, fn (Authenticate $auth) => $auth->isSessionCurrent() && $auth->check(), $phoneRemember);

        $this->assertFalse($current);
    }

    public function test_a_stale_remember_cookie_does_not_sign_out_the_device_holding_the_current_one(): void
    {
        // One token per user: the laptop, remembered last, holds the current one.
        [, $phoneRemember] = $this->signInRemembered('Phone');
        [$laptop, $laptopRemember] = $this->signInRemembered('Laptop');

        $issued = [];
        $this->onDevice($laptop, function (Authenticate $auth) use (&$issued): bool {
            return $this->capturingCookies($issued, fn () => $auth->logoutOtherDevices(self::PASSWORD));
        }, $laptopRemember);

        // The phone comes back with its old remember cookie.
        [, $phoneSignedIn] = $this->onDevice(null, fn (Authenticate $auth) => $auth->check(), $phoneRemember);

        $this->assertFalse($phoneSignedIn);
        $this->assertNotNull($this->storedRememberToken(), 'The current remember token survives.');

        [, $laptopSignedIn] = $this->onDevice(null, fn (Authenticate $auth) => $auth->check(), $issued);

        $this->assertTrue($laptopSignedIn, "The laptop's new remember cookie still works.");
    }

    public function test_signing_others_out_right_after_regenerating_the_session_keeps_this_device(): void
    {
        $laptop = $this->signIn('Laptop');
        $phone = $this->signIn('Phone');

        [$laptop] = $this->onDevice($laptop, function (Authenticate $auth, Request $request): bool {
            $request->session()->regenerate();

            return $auth->logoutOtherDevices(self::PASSWORD);
        });

        $this->assertTrue($this->isSignedIn($laptop));
        $this->assertFalse($this->isSignedIn($phone));
        $this->assertSame(1, $this->sessionRows());
    }

    public function test_signing_others_out_right_after_signing_in_again_keeps_this_device(): void
    {
        $laptop = $this->signIn('Laptop');
        $phone = $this->signIn('Phone');

        [$laptop] = $this->onDevice($laptop, function (Authenticate $auth): bool {
            $auth->login(DeviceUser::find(1));

            return $auth->logoutOtherDevices(self::PASSWORD);
        });

        $this->assertTrue($this->isSignedIn($laptop));
        $this->assertFalse($this->isSignedIn($phone));
    }

    public function test_signing_others_out_keeps_this_device_when_a_concurrent_request_rotated_its_id(): void
    {
        $laptop = $this->signIn('Laptop');
        $phone = $this->signIn('Phone');
        $rotated = bin2hex(random_bytes(20));

        $this->onDevice($laptop, function (Authenticate $auth, Request $request) use ($laptop, $rotated): bool {
            $auth->user();

            // Another request of the laptop rotates its id meanwhile and hands
            // the browser a cookie for the new one.
            $this->store->write($rotated, $this->store->read($laptop)['data']);
            $this->store->write($laptop, RotationPointer::make($rotated, time() + 60));

            return $auth->logoutOtherDevices(self::PASSWORD);
        });

        $this->assertTrue($this->isSignedIn($rotated));
        $this->assertFalse($this->isSignedIn($phone));
    }

    public function test_sessions_marks_this_device_as_current_after_regenerating(): void
    {
        $laptop = $this->signIn('Laptop');
        $this->signIn('Phone');

        [, $sessions] = $this->onDevice($laptop, function (Authenticate $auth, Request $request): array {
            $request->session()->regenerate();

            return $auth->sessions();
        });

        $this->assertSame(['Laptop'], array_column(array_filter($sessions, fn (array $s) => $s['is_current']), 'user_agent'));
    }

    public function test_a_password_changed_with_a_query_keeps_this_device_after_refreshing_the_fingerprint(): void
    {
        $this->useStore(new ArrayStore(7200));

        $laptop = $this->signIn('Laptop');
        $phone = $this->signIn('Phone');

        $this->onDevice($laptop, function (Authenticate $auth, Request $request): void {
            (new AuthenticateSession())($request, function (): Response {
                DeviceUser::where('id', 1)->update(['password' => password_hash('a new password', PASSWORD_BCRYPT, ['cost' => 4])]);
                app('auth')->refreshSessionFingerprint();

                return new Response();
            });
        });

        $this->assertTrue($this->isCurrentAndSignedIn($laptop));
        $this->assertFalse($this->isCurrentAndSignedIn($phone));
    }

    public function test_the_middleware_does_not_adopt_a_password_another_device_changed_meanwhile(): void
    {
        $this->useStore(new ArrayStore(7200));

        $laptop = $this->signIn('Laptop');
        $phone = $this->signIn('Phone');

        // A request of the phone is running while the laptop signs it out.
        $this->onDevice($phone, function (Authenticate $auth, Request $request) use ($laptop): void {
            (new AuthenticateSession())($request, function () use ($laptop, $request): Response {
                $this->onDevice($laptop, fn (Authenticate $auth) => $auth->logoutOtherDevices(self::PASSWORD));

                // Back to the phone's request.
                Container::getInstance()->instance('request', $request);
                Container::getInstance()->instance('session', $request->session);
                $_COOKIE = [];

                return new Response();
            });
        });

        $this->assertFalse($this->isCurrentAndSignedIn($phone));
        $this->assertTrue($this->isCurrentAndSignedIn($laptop));
    }

    /**
     * @return array<string, array{0: \Closure(): SessionStoreInterface}>
     */
    public static function serverSideStores(): array
    {
        return [
            'database' => [fn (self $test) => $test->databaseStore()],
            'array' => [fn () => new ArrayStore(7200)],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('serverSideStores')]
    public function test_a_parallel_request_of_this_device_does_not_sign_it_out(\Closure $store): void
    {
        $this->useStore($store($this));

        $laptop = $this->signIn('Laptop');
        $phone = $this->signIn('Phone');

        $this->onDevice($laptop, function (Authenticate $auth, Request $request) use ($laptop): bool {
            $result = $auth->logoutOtherDevices(self::PASSWORD);

            // Another request of the laptop runs before this one saves its session.
            [, $signedIn] = $this->onDevice($laptop, function (Authenticate $auth, Request $request): bool {
                $signedIn = null;

                (new AuthenticateSession())($request, function () use (&$signedIn): Response {
                    $signedIn = app('auth')->check();

                    return new Response();
                });

                return $signedIn;
            });

            $this->backTo($request);
            $this->assertTrue($signedIn, 'The parallel request is still signed in.');

            return $result;
        });

        $this->assertTrue($this->isCurrentAndSignedIn($laptop));
        $this->assertFalse($this->isCurrentAndSignedIn($phone));
    }

    public function test_a_parallel_request_that_read_the_user_before_the_change_does_not_sign_this_device_out(): void
    {
        $laptop = $this->signIn('Laptop');

        // A request of the laptop has loaded its user, then this device signs the others out.
        $this->onDevice($laptop, function (Authenticate $auth, Request $request) use ($laptop): void {
            $auth->user();

            $this->onDevice($laptop, fn (Authenticate $auth) => $auth->logoutOtherDevices(self::PASSWORD));
            $this->backTo($request);

            $this->assertTrue($auth->isSessionCurrent(), 'It checks again against what is stored now.');
        });

        $this->assertTrue($this->isCurrentAndSignedIn($laptop));
    }

    public function test_signing_others_out_through_another_actor_keeps_both_actors_signed_in(): void
    {
        $laptop = $this->signIn('Laptop');

        [$laptop] = $this->onDevice($laptop, function (Authenticate $auth): bool {
            $admin = app('auth')->actor('admin');
            $admin->login(DeviceUser::find(1));

            return $admin->logoutOtherDevices(self::PASSWORD);
        });

        [, $both] = $this->onDevice($laptop, function (Authenticate $auth): array {
            $admin = app('auth')->actor('admin');

            return [$auth->isSessionCurrent() && $auth->check(), $admin->isSessionCurrent() && $admin->check()];
        });

        $this->assertSame([true, true], $both);
    }

    public function test_signing_out_a_session_from_before_fingerprints_keeps_its_other_data(): void
    {
        $this->useStore(new ArrayStore(7200));

        $laptop = $this->signIn('Laptop');
        $this->onDevice($laptop, function (Authenticate $auth, Request $request): void {
            $request->session()->forget('password_hash_web');
            $request->session()->put('cart', [42]);
        });

        [$laptop, $current] = $this->onDevice($laptop, fn (Authenticate $auth) => $auth->isSessionCurrent());
        [, $after] = $this->onDevice($laptop, fn (Authenticate $auth, Request $request) => [$auth->check(), $request->session()->get('cart')]);

        $this->assertFalse($current);
        $this->assertSame([false, [42]], $after);
    }

    public function test_a_model_that_only_overrides_get_auth_password_is_refused(): void
    {
        $pdo = Database::getPdoInstance(self::CONNECTION);
        $pdo->exec('ALTER TABLE users ADD COLUMN passwd TEXT');
        $pdo->exec("UPDATE users SET passwd = password, password = 'not the password'");
        Config::set('auth.actors.web.model', RenamedPasswordUser::class);

        $laptop = $this->onDevice(null, fn (Authenticate $auth) => $auth->login(RenamedPasswordUser::find(1)))[0];
        $before = $this->storedPassword();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('getAuthPasswordName()');

        try {
            $this->onDevice($laptop, fn (Authenticate $auth) => $auth->logoutOtherDevices(self::PASSWORD));
        } finally {
            $this->assertSame($before, $this->storedPassword(), 'Nothing was written.');
        }
    }

    public function test_an_invalid_remember_cookie_does_not_earn_a_new_remember_token(): void
    {
        $laptop = $this->signIn('Laptop');
        $forged = ['remember_doppar_web_' . sha1('doppar') => \Phaseolies\Support\Facades\Crypt::encrypt('1|nottherealtoken|' . password_hash('1nottherealtoken', PASSWORD_BCRYPT, ['cost' => 4]))];

        $issued = [];
        $this->onDevice($laptop, function (Authenticate $auth) use (&$issued): bool {
            return $this->capturingCookies($issued, fn () => $auth->logoutOtherDevices(self::PASSWORD));
        }, $forged);

        $this->assertSame([], $issued, 'No remember cookie was issued.');
        $this->assertNull($this->storedRememberToken());
    }

    public function test_the_fingerprint_does_not_reveal_the_password_hash(): void
    {
        $laptop = $this->signIn('Laptop');

        [, $fingerprint] = $this->onDevice($laptop, fn (Authenticate $auth, Request $request) => $request->session()->get('password_hash_web'));

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $fingerprint);
        $this->assertStringNotContainsString($fingerprint, $this->storedPassword());
    }

    public function test_remember_me_cookies_of_other_devices_stop_working(): void
    {
        $laptop = $this->signIn('Laptop');
        [, $phoneRemember] = $this->signInRemembered('Phone');

        // The phone comes back without a session, with only its remember cookie.
        $phoneReturns = fn (): bool => $this->onDevice(null, fn (Authenticate $auth) => $auth->check(), $phoneRemember)[1];

        $this->assertNotSame([], $phoneRemember, 'The phone received a remember cookie.');
        $this->assertTrue($phoneReturns(), 'Before: the remember cookie signs the phone back in.');

        $this->onDevice($laptop, fn (Authenticate $auth) => $auth->logoutOtherDevices(self::PASSWORD));

        $this->assertFalse($phoneReturns(), 'After: the remember cookie no longer works.');
    }

    public function test_a_remembered_device_stays_remembered_after_signing_others_out(): void
    {
        [$laptop, $laptopRemember] = $this->signInRemembered('Laptop');

        $issued = [];
        $this->onDevice($laptop, function (Authenticate $auth) use (&$issued): bool {
            return $this->capturingCookies($issued, fn () => $auth->logoutOtherDevices(self::PASSWORD));
        }, $laptopRemember);

        $this->assertNotNull($this->storedRememberToken(), 'This device keeps a remember token.');
        $this->assertNotSame([], $issued, 'This device received its new remember cookie.');

        [, $signedIn] = $this->onDevice(null, fn (Authenticate $auth) => $auth->check(), $issued);

        $this->assertTrue($signedIn);
    }

    public function test_credentials_are_saved_even_when_the_model_does_not_list_them_as_creatable(): void
    {
        Config::set('auth.actors.web.model', LockedDownUser::class);

        $laptop = $this->onDevice(null, fn (Authenticate $auth) => $auth->login(LockedDownUser::find(1)))[0];
        $phone = $this->onDevice(null, fn (Authenticate $auth) => $auth->login(LockedDownUser::find(1)))[0];
        $before = $this->storedPassword();

        [, $result] = $this->onDevice($laptop, fn (Authenticate $auth) => $auth->logoutOtherDevices(self::PASSWORD));

        $this->assertTrue($result);
        $this->assertNotSame($before, $this->storedPassword(), 'The new hash reached the database.');
        $this->assertFalse($this->isSignedIn($phone));
        $this->assertTrue($this->isSignedIn($laptop));
    }

    public function test_a_users_table_without_a_remember_token_column_is_supported(): void
    {
        Database::getPdoInstance(self::CONNECTION)->exec('ALTER TABLE users DROP COLUMN remember_token');

        $laptop = $this->signIn('Laptop');

        [, $result] = $this->onDevice($laptop, fn (Authenticate $auth) => $auth->logoutOtherDevices(self::PASSWORD));

        $this->assertTrue($result);
        $this->assertTrue($this->isSignedIn($laptop));
    }

    public function test_sessions_lists_the_devices_without_exposing_session_ids(): void
    {
        $laptop = $this->signIn('Laptop');
        $this->signIn('Phone');

        [, $sessions] = $this->onDevice($laptop, fn (Authenticate $auth) => $auth->sessions());

        $this->assertCount(2, $sessions);
        $this->assertSame(['ip_address', 'user_agent', 'last_activity', 'is_current'], array_keys($sessions[0]));
        $this->assertSame([true], array_values(array_unique(array_filter(array_column($sessions, 'is_current')))));
        $this->assertEqualsCanonicalizing(['Laptop', 'Phone'], array_column($sessions, 'user_agent'));
        $this->assertSame('203.0.113.10', $sessions[0]['ip_address']);
    }

    public function test_sessions_is_empty_with_a_store_that_does_not_track_users(): void
    {
        $this->useStore(new ArrayStore(7200));
        $laptop = $this->signIn('Laptop');

        [, $sessions] = $this->onDevice($laptop, fn (Authenticate $auth) => $auth->sessions());

        $this->assertSame([], $sessions);
    }

    public function test_another_actor_never_deletes_sessions_by_the_default_actors_user_ids(): void
    {
        $laptop = $this->signIn('Laptop');
        $phone = $this->signIn('Phone');

        // The admin actor signs in user 1 too, but its ids are not the ones the store tracks.
        [, $result] = $this->onDevice($laptop, function (Authenticate $auth): bool {
            $admin = app('auth')->actor('admin');
            $admin->login(DeviceUser::find(1));

            return $admin->logoutOtherDevices(self::PASSWORD);
        });

        $this->assertTrue($result);
        $this->assertSame(2, $this->sessionRows(), 'No stored session was deleted.');
        $this->assertTrue($this->isSignedIn($phone), 'The phone is only signed out once its fingerprint is checked.');
        $this->assertFalse($this->isCurrentAndSignedIn($phone));
    }

    /**
     * Sign the user in on a new device and return its session cookie.
     */
    private function signIn(string $agent): string
    {
        [$cookie] = $this->onDevice(null, fn (Authenticate $auth) => $auth->login(DeviceUser::find(1)), [], $agent);

        return $cookie;
    }

    /**
     * Sign the user in with "remember me" and return [session cookie, remember cookies].
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function signInRemembered(string $agent): array
    {
        $issued = [];

        [$cookie] = $this->onDevice(null, function (Authenticate $auth) use (&$issued): bool {
            return $this->capturingCookies($issued, fn () => $auth->login(DeviceUser::find(1), true));
        }, [], $agent);

        return [$cookie, $issued];
    }

    /**
     * Run a request on a device and return [the device's session cookie afterwards, the result].
     *
     * @param array<string, string> $cookies Other cookies the device sends, such as remember-me
     * @return array{0: string|null, 1: mixed}
     */
    private function onDevice(?string $sessionCookie, callable $action, array $cookies = [], string $agent = 'Laptop'): array
    {
        $request = new class extends Request {
            public function __construct()
            {
            }
        };

        $request->cookies = new InputBag(array_filter([self::COOKIE => $sessionCookie]));
        $request->server = new ServerBag(['REMOTE_ADDR' => '203.0.113.10', 'REQUEST_URI' => '/', 'REQUEST_METHOD' => 'GET']);
        $request->headers = new HeaderBag(['USER_AGENT' => $agent]);
        $request->query = new InputBag();
        $request->request = new InputBag();
        $request->session = new Session($this->store, $this->sessionConfig);
        $request->session->attach($request);

        $container = Container::getInstance();
        $container->instance('request', $request);
        $container->instance('session', $request->session);
        app('auth')->forgetActors();

        $_COOKIE = $cookies;

        $result = $action(app('auth')->actor('web'), $request);

        $response = new Response();
        $request->session->commit($response);

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === self::COOKIE) {
                $sessionCookie = $cookie->getValue() === '' ? null : $cookie->getValue();
            }
        }

        return [$sessionCookie, $result];
    }

    /**
     * Carry on with a request after another one ran in the middle of it.
     */
    private function backTo(Request $request): void
    {
        Container::getInstance()->instance('request', $request);
        Container::getInstance()->instance('session', $request->session);
        $_COOKIE = [];
    }

    private function isSignedIn(?string $sessionCookie): bool
    {
        return $this->onDevice($sessionCookie, fn (Authenticate $auth) => $auth->check())[1];
    }

    private function isCurrentAndSignedIn(?string $sessionCookie): bool
    {
        return $this->onDevice($sessionCookie, fn (Authenticate $auth) => $auth->isSessionCurrent() && $auth->check())[1];
    }

    /**
     * Record the remember cookies sent to the device while running a callback.
     *
     * @param array<string, string> $issued
     */
    private function capturingCookies(array &$issued, callable $callback): mixed
    {
        CookieRecordingAuthenticate::$sent = [];

        $result = $callback();

        $issued = CookieRecordingAuthenticate::$sent + $issued;

        return $result;
    }

    public function databaseStore(): DatabaseStore
    {
        return new DatabaseStore(
            static fn (): PDO => Database::getPdoInstance(self::CONNECTION),
            'sessions',
            7200,
            SessionManager::userResolver(),
            static fn (): array => ['ip_address' => request()->ip(), 'user_agent' => request()->userAgent()]
        );
    }

    private function useStore(SessionStoreInterface $store): void
    {
        $this->store = $store;
        SessionManager::useStore($store);
    }

    private function storedPassword(): string
    {
        return (string) Database::getPdoInstance(self::CONNECTION)->query('SELECT password FROM users WHERE id = 1')->fetchColumn();
    }

    private function storedRememberToken(): ?string
    {
        $token = Database::getPdoInstance(self::CONNECTION)->query('SELECT remember_token FROM users WHERE id = 1')->fetchColumn();

        return $token === null || $token === false ? null : (string) $token;
    }

    private function sessionRows(): int
    {
        return (int) Database::getPdoInstance(self::CONNECTION)->query('SELECT COUNT(*) FROM sessions')->fetchColumn();
    }

    private function resetConfig(): void
    {
        $reflection = new \ReflectionClass(Config::class);

        foreach (['config' => [], 'cacheFile' => null, 'loadedFromCache' => false, 'fileHashes' => []] as $name => $value) {
            if ($reflection->hasProperty($name)) {
                $reflection->getProperty($name)->setValue(null, $value);
            }
        }
    }
}
