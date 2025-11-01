<?php

namespace Phaseolies\Providers;

use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Psr\SimpleCache\CacheInterface;
use Phaseolies\Providers\ServiceProvider;
use Phaseolies\Cache\CacheStore;

class CacheServiceProvider extends ServiceProvider
{
    /**
     * @var \Closure[] Custom adapter factories
     */
    protected array $customAdapters = [];

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $adapter = $this->createAdapter(config('caching.default', 'file'));
        $cacheStore = new CacheStore($adapter, config('caching.prefix'));
        $this->app->singleton(CacheInterface::class, fn() => $cacheStore);
        $this->app->singleton('cache', fn() => $cacheStore);
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

        return match ($storeConfig['driver'] ?? null) {
            'apc' => new ApcuAdapter(config('caching.prefix')),
            'file' => new FilesystemAdapter(
                config('caching.prefix'),
                0,
                $storeConfig['path'] ?? storage_path('framework/cache/data')
            ),
            'array' => new ArrayAdapter(
                0,
                $storeConfig['serialize'] ?? false
            ),
            'redis' => $this->createRedisAdapter($storeConfig),
            default => $this->createCustomAdapter($store, $storeConfig)
        };
    }

    /**
     * Create Redis adapter
     *
     * @param array $config
     * @return RedisAdapter
     */
    protected function createRedisAdapter(array $config): RedisAdapter
    {
        $redis = new \Redis();

        $dsn = $config['connection'] ?? 'redis://127.0.0.1:6379';
        $parsed = parse_url($dsn);

        $host = $parsed['host'] ?? '127.0.0.1';
        $port = $parsed['port'] ?? 6379;
        $password = $parsed['pass'] ?? null;
        $database = isset($parsed['path']) ? (int) substr($parsed['path'], 1) : 0;

        if (!$redis->connect($host, $port, 2.5)) {
            throw new \RuntimeException("Could not connect to Redis at {$host}:{$port}");
        }

        if ($password !== null) {
            $redis->auth($password);
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

        return new RedisAdapter(
            $redis,
            config('caching.prefix'),
            $config['ttl'] ?? 0
        );
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

        throw new \RuntimeException("Cache store [{$store}] is not defined.");
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
    public function boot()
    {
        //
    }
}
