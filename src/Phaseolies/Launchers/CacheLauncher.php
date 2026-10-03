<?php

namespace Phaseolies\Launchers;

use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Psr\SimpleCache\CacheInterface;
use Phaseolies\Launchers\ServiceLauncher;
use Phaseolies\Launchers\GhostableLauncher;
use Phaseolies\Cache\CacheStore;
use Phaseolies\Cache\IncrementableCacheInterface;

class CacheLauncher extends ServiceLauncher implements GhostableLauncher
{
    /**
     * @var \Closure[] Custom adapter factories
     */
    protected array $customAdapters = [];

    /**
     * @var CacheStore[] Stores that have been built, by name
     */
    protected array $stores = [];

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $default = (string) config('caching.default', 'file');
        $cacheStore = $this->stores[$default] = $this->createStore($default);

        $this->app->singleton(CacheStore::class, fn() => $cacheStore);
        $this->app->singleton(IncrementableCacheInterface::class, fn() => $cacheStore);
        $this->app->singleton(CacheInterface::class, fn() => $cacheStore);
        $this->app->singleton('cache', fn() => $cacheStore);
    }

    /**
     * Build the cache store for a name in `caching.stores`. Every store can reach
     * the others, so `Cache::store('redis')` works from any of them.
     *
     * @param string $name
     * @return CacheStore
     */
    public function createStore(string $name): CacheStore
    {
        $config = config("caching.stores.{$name}");
        $config = is_array($config) ? $config : [];

        $store = new CacheStore(
            $this->createAdapter($name),
            $this->prefixFor($name),
            isset($config['ttl']) && (int) $config['ttl'] > 0 ? (int) $config['ttl'] : null
        );

        return $store->resolveStoresUsing($name, function (string $other) {
            return $this->stores[$other] ??= $this->createStore($other);
        });
    }

    /**
     * The key prefix of a store. It names the adapter's namespace too, so it is
     * limited to characters every backend accepts, and it is never empty: with an
     * empty namespace, clearing the cache would flush the whole Redis database.
     *
     * @param string $store
     * @return string
     */
    protected function prefixFor(string $store): string
    {
        $prefix = config("caching.stores.{$store}.prefix") ?? config('caching.prefix');
        $prefix = preg_replace('/[^-+_.A-Za-z0-9]/', '_', (string) $prefix);

        return $prefix === '' ? 'doppar_cache_' : $prefix;
    }

    /**
     * Create the cache adapter
     *
     * @param string $store
     * @return mixed
     */
    public function createAdapter(string $store): mixed
    {
        $storeConfig = config("caching.stores.{$store}");
        $storeConfig = is_array($storeConfig) ? $storeConfig : [];
        $prefix = $this->prefixFor($store);

        // The default ttl is applied by CacheStore, not by the adapter, so that
        // forever() really is forever.
        return match ($storeConfig['driver'] ?? null) {
            'apc' => new ApcuAdapter($prefix),
            'file' => new FilesystemAdapter(
                $prefix,
                0,
                $storeConfig['path'] ?? storage_path('framework/cache/data')
            ),
            'array' => new ArrayAdapter(
                0,
                $storeConfig['serialize'] ?? false
            ),
            'redis' => $this->createRedisAdapter($storeConfig, $prefix),
            default => $this->createCustomAdapter($store, $storeConfig)
        };
    }

    /**
     * Create Redis adapter
     *
     * @param array $config
     * @return RedisAdapter
     */
    protected function createRedisAdapter(array $config, ?string $prefix = null): RedisAdapter
    {
        $redis = new \Redis();

        $dsn = $config['connection'] ?? 'redis://127.0.0.1:6379';
        $parsed = parse_url($dsn);
        $parameters = $config['options']['parameters'] ?? [];

        $host = $parsed['host'] ?? '127.0.0.1';
        $port = $parsed['port'] ?? 6379;

        // The connection string wins; `options.parameters` fills in what it leaves out.
        $username = isset($parsed['user']) && $parsed['user'] !== '' ? urldecode($parsed['user']) : null;
        $password = isset($parsed['pass']) && $parsed['pass'] !== ''
            ? urldecode($parsed['pass'])
            : (($parameters['password'] ?? '') !== '' ? (string) $parameters['password'] : null);

        $path = trim($parsed['path'] ?? '', '/');
        $database = $path !== '' ? (int) $path : (int) ($parameters['database'] ?? 0);

        if (!$redis->connect($host, $port, 2.5)) {
            throw new \RuntimeException("Could not connect to Redis at {$host}:{$port}");
        }

        if ($password !== null) {
            $redis->auth($username !== null ? [$username, $password] : $password);
        }

        if ($database > 0) {
            $redis->select($database);
        }

        foreach ($config['options'] ?? [] as $name => $value) {
            $optionConstant = $this->getRedisOptionConstant($name);
            if ($optionConstant !== null) {
                $redis->setOption($optionConstant, $value);
            }
        }

        return new RedisAdapter($redis, $prefix ?? $this->prefixFor('redis'), 0);
    }

    /**
     * Map string option names to Redis constants
     *
     * @param string $name
     * @return int|null
     */
    protected function getRedisOptionConstant(string $name): ?int
    {
        $constants = [
            'serializer' => \Redis::OPT_SERIALIZER,
            'prefix' => \Redis::OPT_PREFIX,
            'read_timeout' => \Redis::OPT_READ_TIMEOUT,
            'scan' => \Redis::OPT_SCAN,
            'compression' => \Redis::OPT_COMPRESSION,
        ];

        return $constants[strtolower($name)] ?? null;
    }

    /**
     * Create custom adapter
     *
     * @param string $store
     * @param array $config
     * @return mixed
     */
    protected function createCustomAdapter(string $store, array $config): mixed
    {
        if (isset($this->customAdapters[$store])) {
            return $this->customAdapters[$store]($config);
        }

        if ($config === []) {
            throw new \RuntimeException("Cache store [{$store}] is not defined.");
        }

        throw new \RuntimeException(sprintf(
            'Cache driver [%s] of store [%s] is not supported. Use apc, array, file or redis, or register it with extend().',
            $config['driver'] ?? 'none',
            $store
        ));
    }

    /**
     * Generate custom adapter
     * @param string $store
     * @param \Closure $factory
     * @return void
     */
    public function extend(string $store, \Closure $factory): void
    {
        $this->customAdapters[$store] = $factory;
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function launch()
    {
        //
    }

    /**
     * Get the services that should ghost-load this provider.
     *
     * @return array<int, string>
     */
    public function ghosts(): array
    {
        return [
            CacheStore::class,
            IncrementableCacheInterface::class,
            CacheInterface::class,
            'cache',
        ];
    }
}
