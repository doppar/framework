<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Cache\CacheStore get($key, $default = null): mixed
 * @method static \Phaseolies\Cache\CacheStore set($key, $value, $ttl = null): bool
 * @method static \Phaseolies\Cache\CacheStore delete($key): bool
 * @method static \Phaseolies\Cache\CacheStore clear(): bool
 * @method static \Phaseolies\Cache\CacheStore getMultiple($keys, $default = null): iterable
 * @method static \Phaseolies\Cache\CacheStore setMultiple($values, $ttl = null): bool
 * @method static \Phaseolies\Cache\CacheStore deleteMultiple($keys): bool
 * @method static \Phaseolies\Cache\CacheStore has($key): bool
 * @method static \Phaseolies\Cache\CacheStore increment($key, $value = 1): int|bool
 * @method static \Phaseolies\Cache\CacheStore decrement($key, $value = 1): int|bool
 * @method static \Phaseolies\Cache\CacheStore forever($key, $value): bool
 * @method static \Phaseolies\Cache\CacheStore forget($key)
 * @see \Phaseolies\Cache\CacheStore
 */

use Phaseolies\Facade\BaseFacade;

class Cache extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'cache';
    }
}
