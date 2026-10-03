<?php

namespace Tests\Unit\Cache;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Phaseolies\Cache\CacheStore;
use Phaseolies\Application;
use Phaseolies\Config\Config;
use Phaseolies\DI\Container;
use Phaseolies\Launchers\CacheLauncher;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Tests\Support\MockContainer;

class CacheLauncherTest extends TestCase
{
    private MockContainer $container;

    protected function setUp(): void
    {
        $this->container = new MockContainer();
        Container::setInstance($this->container);
        $this->resetConfig();

        Config::set('caching.default', 'array');
        Config::set('caching.prefix', 'app_cache_');
        Config::set('caching.stores', [
            'array' => ['driver' => 'array'],
            'other' => ['driver' => 'array', 'ttl' => 90, 'prefix' => 'other_'],
            'file' => ['driver' => 'file', 'path' => sys_get_temp_dir() . '/doppar-launcher-' . bin2hex(random_bytes(4))],
        ]);
    }

    protected function tearDown(): void
    {
        $this->resetConfig();
        Container::forgetInstance();
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

    /** @var array<string, \Closure> What register() bound as singletons */
    private array $bindings = [];

    private function launcher(): CacheLauncher
    {
        $app = $this->createStub(Application::class);
        $app->method('singleton')->willReturnCallback(function (string $abstract, $concrete = null) {
            $this->bindings[$abstract] = $concrete;
        });

        $launcher = (new \ReflectionClass(CacheLauncher::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($launcher, 'app'))->setValue($launcher, $app);

        return $launcher;
    }

    private function bound(string $abstract): mixed
    {
        return ($this->bindings[$abstract])();
    }

    private function prefixFor(string $store): string
    {
        return (new \ReflectionMethod(CacheLauncher::class, 'prefixFor'))->invoke($this->launcher(), $store);
    }

    // ----- prefix -----------------------------------------------------------------------

    public function testAnEmptyPrefixFallsBackToASafeOne(): void
    {
        Config::set('caching.prefix', '');

        $this->assertSame('doppar_cache_', $this->prefixFor('array'));
    }

    public function testAPrefixIsMadeSafeForEveryBackend(): void
    {
        Config::set('caching.prefix', 'my app/v2:cache_');

        $this->assertSame('my_app_v2_cache_', $this->prefixFor('array'));
    }

    public function testAStoreCanHaveItsOwnPrefix(): void
    {
        $this->assertSame('other_', $this->prefixFor('other'));
        $this->assertSame('app_cache_', $this->prefixFor('array'));
    }

    public function testAFileStoreSurvivesAnAppNameWithSpaces(): void
    {
        Config::set('caching.prefix', strtolower('My App') . '_cache_');

        $adapter = $this->launcher()->createAdapter('file');

        $this->assertInstanceOf(FilesystemAdapter::class, $adapter);
    }

    // ----- errors -----------------------------------------------------------------------

    public function testAnUnknownStoreIsReportedClearly(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cache store [memcached] is not defined.');

        $this->launcher()->createAdapter('memcached');
    }

    public function testAnUnsupportedDriverIsReportedClearly(): void
    {
        Config::set('caching.stores.weird', ['driver' => 'memcached']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cache driver [memcached] of store [weird] is not supported');

        $this->launcher()->createAdapter('weird');
    }

    public function testACustomDriverCanBeRegistered(): void
    {
        Config::set('caching.stores.custom', ['driver' => 'mine']);
        $launcher = $this->launcher();
        $launcher->extend('custom', fn(array $config) => new ArrayAdapter());

        $this->assertInstanceOf(ArrayAdapter::class, $launcher->createAdapter('custom'));
    }

    // ----- stores -----------------------------------------------------------------------

    public function testTheStoreDefaultTtlComesFromTheConfig(): void
    {
        $store = $this->launcher()->createStore('other');
        $store->set('k', 1);
        $adapter = $store->getAdapter();

        $expiry = $adapter->getItem('other_k')->getMetadata()['expiry'];
        $this->assertEqualsWithDelta(time() + 90, $expiry, 2);

        $plain = $this->launcher()->createStore('array');
        $plain->set('k', 1);
        $this->assertNull($plain->getAdapter()->getItem('app_cache_k')->getMetadata()['expiry'] ?? null);
    }

    public function testRegisterBindsTheDefaultStoreAndOthersAreReachableThroughStore(): void
    {
        $launcher = $this->launcher();
        $launcher->register();

        /** @var CacheStore $cache */
        $cache = $this->bound('cache');
        $this->assertSame($cache, $this->bound(CacheStore::class));

        $this->assertSame($cache, $cache->store('array'), 'the default store resolves to the bound instance');

        $other = $cache->store('other');
        $this->assertNotSame($cache, $other);
        $this->assertSame($other, $cache->store('other'), 'a store is built once');
        $this->assertSame($cache, $other->store('array'), 'every store can reach the default one');

        $cache->set('who', 'default');
        $other->set('who', 'other');
        $this->assertSame('default', $cache->get('who'));
        $this->assertSame('other', $other->get('who'));
    }

    public function testAskingForAnUnknownStoreThrows(): void
    {
        $launcher = $this->launcher();
        $launcher->register();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cache store [nope] is not defined.');

        $this->bound('cache')->store('nope');
    }

    // ----- redis (opt-in) ---------------------------------------------------------------

    /**
     * Needs a Redis server and a database you do not mind losing:
     *
     *   DOPPAR_TEST_REDIS_HOST=127.0.0.1 DOPPAR_TEST_REDIS_DB=13 vendor/bin/phpunit --group redis
     *
     * The database must be empty; the tests flush it.
     */
    private function redis(): \Redis
    {
        $host = getenv('DOPPAR_TEST_REDIS_HOST') ?: '';
        $database = getenv('DOPPAR_TEST_REDIS_DB');

        if ($host === '' || $database === false || !extension_loaded('redis')) {
            $this->markTestSkipped('Set DOPPAR_TEST_REDIS_HOST and DOPPAR_TEST_REDIS_DB (an empty scratch database) to run this test.');
        }

        $redis = new \Redis();
        $redis->connect($host, (int) (getenv('DOPPAR_TEST_REDIS_PORT') ?: 6379));
        $redis->select((int) $database);

        if ($redis->dbSize() !== 0) {
            $this->markTestSkipped("Redis database {$database} is not empty, refusing to use it.");
        }

        return $redis;
    }

    private function redisConfig(array $overrides = []): array
    {
        $host = getenv('DOPPAR_TEST_REDIS_HOST');
        $port = getenv('DOPPAR_TEST_REDIS_PORT') ?: 6379;

        return array_merge(['driver' => 'redis', 'connection' => "redis://{$host}:{$port}"], $overrides);
    }

    #[Group('redis')]
    public function testForeverIsReallyForeverOnRedisWhileSetUsesTheConfiguredTtl(): void
    {
        $redis = $this->redis();
        Config::set('caching.stores.redis', $this->redisConfig([
            'ttl' => 3600,
            'options' => ['parameters' => ['database' => getenv('DOPPAR_TEST_REDIS_DB')]],
        ]));

        try {
            $store = $this->launcher()->createStore('redis');
            $store->set('plain', 1);
            $store->forever('perm', 2);
            $store->stashForever('perm2', fn() => 3);

            $this->assertEqualsWithDelta(3600, $redis->ttl($redis->keys('*plain')[0]), 5);
            $this->assertSame(-1, $redis->ttl($redis->keys('*perm')[0]), 'forever() must not expire');
            $this->assertSame(-1, $redis->ttl($redis->keys('*perm2')[0]), 'stashForever() must not expire');
        } finally {
            $redis->flushDB();
        }
    }

    #[Group('redis')]
    public function testClearingAStoreWithNoConfiguredPrefixLeavesOtherKeysAlone(): void
    {
        $redis = $this->redis();
        Config::set('caching.prefix', '');
        Config::set('caching.stores.redis', $this->redisConfig([
            'options' => ['parameters' => ['database' => getenv('DOPPAR_TEST_REDIS_DB')]],
        ]));

        try {
            $redis->set('someone_elses_session', 'keep me');

            $store = $this->launcher()->createStore('redis');
            $store->set('mine', 1);
            $store->clear();

            $this->assertSame('keep me', $redis->get('someone_elses_session'));
            $this->assertFalse($store->has('mine'));
        } finally {
            $redis->flushDB();
        }
    }

    #[Group('redis')]
    public function testTheDatabaseFromOptionsParametersIsUsed(): void
    {
        $redis = $this->redis();
        Config::set('caching.stores.redis', $this->redisConfig([
            'options' => ['parameters' => ['database' => getenv('DOPPAR_TEST_REDIS_DB'), 'password' => '']],
        ]));

        try {
            $this->launcher()->createStore('redis')->set('where', 'here');

            $this->assertCount(1, $redis->keys('*where'), 'written to the configured database');
        } finally {
            $redis->flushDB();
        }
    }

    #[Group('redis')]
    public function testTagsWorkOnRedis(): void
    {
        $redis = $this->redis();
        Config::set('caching.stores.redis', $this->redisConfig([
            'options' => ['parameters' => ['database' => getenv('DOPPAR_TEST_REDIS_DB')]],
        ]));

        try {
            $store = $this->launcher()->createStore('redis');
            $store->tags('users')->set('user.1', 'ada');
            $store->tags('posts')->set('post.1', 'hello');
            $store->set('plain', 'p');

            $store->tags('users')->flush();

            $this->assertFalse($store->tags('users')->has('user.1'));
            $this->assertSame('hello', $store->tags('posts')->get('post.1'));
            $this->assertSame('p', $store->get('plain'));

            $store->clear();
            $this->assertFalse($store->tags('posts')->has('post.1'));
        } finally {
            $redis->flushDB();
        }
    }
}
