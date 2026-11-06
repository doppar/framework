<?php

namespace Tests\Unit\Session;

use Phaseolies\Config\Config;
use Phaseolies\DI\Container;
use Phaseolies\Session\SessionManager;
use Phaseolies\Session\Stores\DatabaseStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\MockContainer;

class SessionManagerTest extends TestCase
{
    #[DataProvider('names')]
    public function test_cookie_names_survive_phps_cookie_name_mangling(string $input, string $expected): void
    {
        $name = SessionManager::cookieName($input);

        $this->assertSame($expected, $name);

        // PHP turns dots and spaces into underscores when it builds $_COOKIE,
        // so the name must come back unchanged after that rewrite.
        $this->assertSame($name, str_replace(['.', ' '], '_', $name));
    }

    public static function names(): array
    {
        return [
            'plain' => ['doppar_session', 'doppar_session'],
            'space' => ['my app_session', 'my_app_session'],
            'dot' => ['my.app_session', 'my_app_session'],
            'hyphen kept' => ['my-app_session', 'my-app_session'],
            'unicode and separators' => ["caf\u{e9} \"x\";y", 'caf_x_y'],
            'empty falls back' => ['', 'doppar_session'],
            'only junk falls back' => ['   ', 'doppar_session'],
        ];
    }

    #[DataProvider('lotteries')]
    public function test_the_lottery_option_is_normalized(mixed $input, array $expected): void
    {
        $this->assertSame($expected, SessionManager::lottery($input));
        $this->assertSame($expected, SessionManager::normalize(['lottery' => $input])['lottery']);
    }

    public static function lotteries(): array
    {
        return [
            'default' => [[2, 100], [2, 100]],
            'null never sweeps' => [null, [0, 100]],
            'false never sweeps' => [false, [0, 100]],
            'empty array never sweeps' => [[], [0, 100]],
            'zero chances never sweeps' => [[0, 100], [0, 100]],
            'not an array never sweeps' => ['2/100', [0, 100]],
            'a single number keeps the default total' => [[5], [5, 100]],
            'a bad total falls back' => [[2, 0], [2, 100]],
            'negative chances never sweep' => [[-3, 100], [0, 100]],
            'string numbers are read' => [['3', '50'], [3, 50]],
            'every request' => [[1, 1], [1, 1]],
        ];
    }

    public function test_a_config_without_a_lottery_gets_the_default(): void
    {
        $this->assertSame([2, 100], SessionManager::normalize([])['lottery']);
    }

    public function test_a_config_without_database_options_gets_the_defaults(): void
    {
        $config = SessionManager::normalize([]);

        $this->assertNull($config['connection']);
        $this->assertSame('sessions', $config['table']);
    }

    public function test_the_database_driver_builds_a_database_store_without_connecting(): void
    {
        $this->withAuthConfig(function (): void {
            $store = SessionManager::createStore(SessionManager::normalize([
                'driver' => 'database',
                'connection' => 'not_configured_so_connecting_would_fail',
            ]));

            $this->assertInstanceOf(DatabaseStore::class, $store);
            $this->assertFalse($store->isClientSide());
        });
    }

    public function test_sessions_are_linked_to_the_user_of_the_default_actor(): void
    {
        $this->withAuthConfig(function (): void {
            $resolve = SessionManager::userResolver();

            $this->assertSame('user', SessionManager::userSessionKey());
            $this->assertSame(5, $resolve(['user' => 5, 'admin' => 9]));
            $this->assertNull($resolve(['cart' => []]));
        });
    }

    public function test_without_an_auth_actor_sessions_are_not_linked_to_users(): void
    {
        $this->withAuthConfig(function (): void {
            Config::set('auth.default', 'missing');

            $this->assertNull(SessionManager::userSessionKey());
            $this->assertNull(SessionManager::userResolver());
        });
    }

    /**
     * Run a test with a container and an auth config where "web" keeps the user under "user".
     *
     * @param callable $test
     * @return void
     */
    private function withAuthConfig(callable $test): void
    {
        Container::setInstance(new MockContainer());

        $previous = (new \ReflectionClass(Config::class))->getProperty('config')->getValue();

        Config::set('auth.default', 'web');
        Config::set('auth.actors.web.session_key', 'user');
        Config::set('auth.actors.admin.session_key', 'admin');

        try {
            $test();
        } finally {
            (new \ReflectionClass(Config::class))->getProperty('config')->setValue(null, $previous);
            (new \ReflectionClass(Container::class))->getProperty('instance')->setValue(null, null);
        }
    }
}
