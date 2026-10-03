<?php

namespace Phaseolies\Cache;

use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class TaggedCache
{
    /**
     * @param CacheStore $store
     * @param TagAwareAdapter $pool
     * @param array<int, string> $tags
     */
    public function __construct(
        protected CacheStore $store,
        protected TagAwareAdapter $pool,
        protected array $tags
    ) {
        if ($this->tags === []) {
            throw new \InvalidArgumentException('At least one cache tag is required.');
        }
    }

    /**
     * Get a tagged item.
     *
     * @param mixed $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null): mixed
    {
        $item = $this->pool->getItem($this->store->validatedKey($key));

        return $item->isHit() ? $item->get() : $default;
    }

    /**
     * Store an item with this cache's tags.
     *
     * @param mixed $key
     * @param mixed $value
     * @param null|int|\DateInterval $ttl
     * @return bool
     */
    public function set($key, $value, $ttl = null): bool
    {
        $item = $this->pool->getItem($this->store->validatedKey($key));
        $item->set($value)->tag($this->tags);

        $seconds = $this->store->ttlSeconds($ttl);

        if ($seconds !== null) {
            $item->expiresAfter($seconds);
        }

        return $this->pool->save($item);
    }

    /**
     * Store an item with this cache's tags until it is flushed.
     *
     * @param mixed $key
     * @param mixed $value
     * @return bool
     */
    public function forever($key, $value): bool
    {
        $item = $this->pool->getItem($this->store->validatedKey($key));
        $item->set($value)->tag($this->tags)->expiresAfter(null);

        return $this->pool->save($item);
    }

    /**
     * Determine whether a tagged item exists.
     *
     * @param mixed $key
     * @return bool
     */
    public function has($key): bool
    {
        return $this->pool->hasItem($this->store->validatedKey($key));
    }

    /**
     * Remove one tagged item.
     *
     * @param mixed $key
     * @return bool
     */
    public function delete($key): bool
    {
        return $this->pool->deleteItem($this->store->validatedKey($key));
    }

    /**
     * Remove one tagged item.
     *
     * @param mixed $key
     * @return bool
     */
    public function forget($key): bool
    {
        return $this->delete($key);
    }

    /**
     * Get a tagged item and remove it in one step.
     *
     * @param mixed $key
     * @param mixed $default
     * @return mixed
     */
    public function pull($key, $default = null): mixed
    {
        $key = $this->store->validatedKey($key);
        $item = $this->pool->getItem($key);

        if (!$item->isHit()) {
            return $default;
        }

        $value = $item->get();
        $this->pool->deleteItem($key);

        return $value;
    }

    /**
     * Get a tagged item, or run the callback and store its result with these tags.
     *
     * @param string $key
     * @param int|\DateInterval|null $ttl
     * @param \Closure $callback
     * @return mixed
     */
    public function stash(string $key, $ttl, \Closure $callback): mixed
    {
        return $this->remember($key, $this->store->ttlSeconds($ttl), $callback);
    }

    /**
     * Get a tagged item, or run the callback and store its result until it is flushed.
     *
     * @param string $key
     * @param \Closure $callback
     * @return mixed
     */
    public function stashForever(string $key, \Closure $callback): mixed
    {
        return $this->remember($key, null, $callback);
    }

    /**
     * Remove every item that has any of this cache's tags.
     *
     * @return bool
     */
    public function flush(): bool
    {
        return $this->pool->invalidateTags($this->tags);
    }

    /**
     * The tags this cache writes with.
     *
     * @return array<int, string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param string $key
     * @param int|null $seconds
     * @param \Closure $callback
     * @return mixed
     */
    protected function remember(string $key, ?int $seconds, \Closure $callback): mixed
    {
        return $this->pool->get(
            $this->store->validatedKey($key),
            function (ItemInterface $item) use ($seconds, $callback) {
                $item->tag($this->tags)->expiresAfter($seconds);

                return $callback();
            }
        );
    }
}
