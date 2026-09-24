<?php

namespace Phaseolies\Support\Facades;

use Closure;
use Phaseolies\Facade\BaseFacade;
use Phaseolies\Cache\Lock\AtomicLock;

/**
 * @method static mixed get($key, $default = null)
 * @method static bool set($key, $value, $ttl = null)
 * @method static bool delete($key)
 * @method static bool clear()
 * @method static iterable getMultiple($keys, $default = null)
 * @method static bool setMultiple($values, $ttl = null)
 * @method static bool deleteMultiple($keys)
 * @method static bool has($key)
 * @method static int|bool increment($key, $value = 1)
 * @method static int|bool decrement($key, $value = 1)
 * @method static bool forever($key, $value)
 * @method static bool forget($key)
 * @method static mixed stash(string $key, $ttl, Closure $callback)
 * @method static mixed stashForever(string $key, Closure $callback)
 * @method static mixed stashWhen(string $key, Closure $callback, bool $condition, $ttl = null)
 * @method static AtomicLock locked(string $name, int $seconds = 10, ?string $owner = null)
 * @method static AtomicLock restoreLock(string $name, string $owner)
 * @method static bool get()
 * @method static bool block(int $seconds)
 * @method static bool release()
 * @method static string owner()
 * @method static bool isOwnedByCurrentProcess()
 * @method static string getOwner()
 * @method static string getName()
 *
 * @see \Phaseolies\Cache\CacheStore
 * @see \Phaseolies\Cache\Lock\AtomicLock
 */
class Cache extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'cache';
    }
}
