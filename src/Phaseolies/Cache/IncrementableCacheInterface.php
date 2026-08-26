<?php

namespace Phaseolies\Cache;

use Psr\SimpleCache\CacheInterface;

interface IncrementableCacheInterface extends CacheInterface
{
    /**
     * Increment the value of an item in the cache.
     *
     * @param string $key
     * @param int $value
     * @return int|bool
     */
    public function increment($key, $value = 1): int|bool;

    /**
     * Store an item in the cache if the key doesn't exist.
     *
     * @param string $key
     * @param mixed $value
     * @param null|int|\DateInterval $ttl
     * @return bool
     */
    public function add($key, $value, $ttl = null): bool;
}
