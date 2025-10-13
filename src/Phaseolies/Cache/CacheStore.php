<?php

namespace Phaseolies\Cache;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Psr\SimpleCache\CacheInterface;
use Phaseolies\Cache\Lock\AtomicLock;

class CacheStore implements CacheInterface
{
    /**
     * The cache adapter instance.
     *
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * The cache prefix
     *
     * @var string
     */
    protected string $prefix;

    /**
     * Create a new cache store instance.
     *
     * @param AdapterInterface $adapter
     * @param string|null $prefix
     * @return void
     */
    public function __construct(AdapterInterface $adapter, ?string $prefix = null)
    {
        $this->adapter = $adapter;
        $this->prefix = $prefix ?? config('caching.prefix');
    }

    /**
     * Set cache prefix
     *
     * @param string $key
     * @return string
     */
    protected function prefixedKey(string $key): string
    {
        return $this->prefix . $key;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null): mixed
    {
        $key = $this->prefixedKey($key);
        $this->validateKey($key);
        $item = $this->adapter->getItem($key);

        return $item->isHit() ? $item->get() : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null): bool
    {
        $key = $this->prefixedKey($key);
        $this->validateKey($key);

        $item = $this->adapter->getItem($key);
        $item->set($value);

        if ($ttl !== null) {
            $item->expiresAfter($this->convertTtlToSeconds($ttl));
        }

        return $this->adapter->save($item);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key): bool
    {
        $key = $this->prefixedKey($key);

        $this->validateKey($key);

        return $this->adapter->deleteItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        return $this->adapter->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null): iterable
    {
        if (!is_iterable($keys)) {
            throw new \InvalidArgumentException('Keys must be an array or traversable');
        }

        $prefixedKeys = array_map(fn($key) => $this->prefixedKey($key), (array)$keys);
        $validatedKeys = $this->validateKeys($prefixedKeys);

        $items = $this->adapter->getItems($validatedKeys);
        $results = [];

        foreach ($items as $key => $item) {
            $originalKey = substr($key, strlen($this->prefix));
            $results[$originalKey] = $item->isHit() ? $item->get() : $default;
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null): bool
    {
        if (!is_iterable($values)) {
            throw new \InvalidArgumentException('Values must be an array or traversable');
        }

        $success = true;
        $ttl = $this->convertTtlToSeconds($ttl);

        foreach ($values as $key => $value) {
            $prefixedKey = $this->prefixedKey($key);
            $this->validateKey($prefixedKey);
            $item = $this->adapter->getItem($prefixedKey);
            $item->set($value);

            if ($ttl !== null) {
                $item->expiresAfter($ttl);
            }

            $success = $success && $this->adapter->save($item);
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys): bool
    {
        if (!is_iterable($keys)) {
            throw new \InvalidArgumentException('Keys must be an array or traversable');
        }

        $prefixedKeys = array_map(fn($key) => $this->prefixedKey($key), (array)$keys);
        $validatedKeys = $this->validateKeys($prefixedKeys);

        return $this->adapter->deleteItems($validatedKeys);
    }

    /**
     * {@inheritdoc}
     */
    public function has($key): bool
    {
        $key = $this->prefixedKey($key);

        $this->validateKey($key);

        return $this->adapter->hasItem($key);
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param string $key
     * @param int $value
     * @return int|bool
     */
    public function increment($key, $value = 1): int|bool
    {
        $key = $this->prefixedKey($key);
        $this->validateKey($key);
        $item = $this->adapter->getItem($key);

        if (!$item->isHit()) {
            return false;
        }

        $current = (int)$item->get();
        $new = $current + $value;
        $item->set($new);

        // Preserve existing expiration
        if ($item->getMetadata()['expiry'] ?? null) {
            $item->expiresAt(\DateTime::createFromFormat('U', $item->getMetadata()['expiry']));
        }

        $this->adapter->save($item);
        return $new;
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @param string $key
     * @param int $value
     * @return int|bool
     */
    public function decrement($key, $value = 1): int|bool
    {
        $key = $this->prefixedKey($key);
        $this->validateKey($key);
        $item = $this->adapter->getItem($key);

        if (!$item->isHit()) {
            return false;
        }

        $current = (int)$item->get();
        $new = $current - $value;
        $item->set($new);

        // Preserve existing expiration
        if ($item->getMetadata()['expiry'] ?? null) {
            $item->expiresAt(\DateTime::createFromFormat('U', $item->getMetadata()['expiry']));
        }

        $this->adapter->save($item);
        return $new;
    }

    /**
     * Store an item in the cache if the key doesn't exist.
     *
     * @param string $key
     * @param mixed $value
     * @param  null|int|\DateInterval  $ttl
     * @return bool
     */
    public function add($key, $value, $ttl = null): bool
    {
        $key = $this->prefixedKey($key);
        $this->validateKey($key);

        if ($this->adapter->hasItem($key)) {
            return false;
        }

        $item = $this->adapter->getItem($key);
        $item->set($value);

        if ($ttl !== null) {
            $item->expiresAfter($this->convertTtlToSeconds($ttl));
        }

        return $this->adapter->save($item);
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function forever($key, $value): bool
    {
        $key = $this->prefixedKey($key);
        $this->validateKey($key);

        $item = $this->adapter->getItem($key);
        $item->set($value);
        $item->expiresAfter(null);

        return $this->adapter->save($item);
    }

    /**
     * Remove an item from the cache.
     *
     * @param string $key
     * @return bool
     */
    public function forget($key): bool
    {
        $key = $this->prefixedKey($key);
        $this->validateKey($key);

        if (!$this->adapter->hasItem($key)) {
            return false;
        }

        return $this->adapter->deleteItem($key);
    }

    /**
     * Validate a cache key.
     *
     * @param string $key
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function validateKey($key): void
    {
        if (!is_string($key)) {
            throw new \InvalidArgumentException(sprintf(
                'Cache key must be string, "%s" given',
                gettype($key)
            ));
        }

        if (preg_match('/[{}()\/\\\\@]/', $key)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid key: "%s". The key contains one or more characters reserved for future extension',
                $key
            ));
        }
    }

    /**
     * Validate an array of cache keys.
     *
     * @param iterable $keys
     * @return array
     */
    protected function validateKeys($keys): array
    {
        $validated = [];
        foreach ($keys as $key) {
            $this->validateKey($key);
            $validated[] = $key;
        }

        return $validated;
    }

    /**
     * Convert TTL to seconds.
     *
     * @param  null|int|\DateInterval  $ttl
     * @return int|null
     */
    protected function convertTtlToSeconds($ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        if ($ttl instanceof \DateInterval) {
            $ttl = (new \DateTime('@0'))->add($ttl)->getTimestamp();
        }

        return (int) $ttl;
    }

    /**
     * Get the current adapter
     *
     * @return AdapterInterface
     */
    public function getAdapter(): AdapterInterface
    {
        return $this->adapter;
    }

    /**
     * Get an item from the cache, or execute the callback and store the result.
     *
     * @param string $key
     * @param int|DateInterval $ttl
     * @param \Closure $callback
     * @return mixed
     */
    public function stash(string $key, $ttl, \Closure $callback): mixed
    {
        $value = $this->get($key);

        if (!is_null($value)) {
            return $value;
        }

        $value = $callback();

        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Get an item from the cache, or execute the callback and store the result forever.
     *
     * @param string $key
     * @param \Closure $callback
     * @return mixed
     */
    public function stashForever(string $key, \Closure $callback): mixed
    {
        $value = $this->get($key);

        if (!is_null($value)) {
            return $value;
        }

        $value = $callback();

        $this->forever($key, $value);

        return $value;
    }

    /**
     * Get an item from the cache, or execute the callback and store the result conditionally.
     *
     * @param string $key
     * @param \Closure $callback
     * @param bool $condition Whether to cache the result
     * @param int|\DateInterval|null $ttl Time to live (optional)
     * @return mixed
     */
    public function stashWhen(string $key, \Closure $callback, bool $condition, $ttl = null): mixed
    {
        if (!$condition) {
            return $callback();
        }

        return $ttl === null
            ? $this->stashForever($key, $callback)
            : $this->stash($key, $ttl, $callback);
    }

    /**
     * Get a lock instance.
     *
     * @param string $name
     * @param int $seconds
     * @param string|null $owner
     * @return \Phaseolies\Cache\Lock\AtomicLock
     */
    public function locked(string $name, int $seconds = 10, ?string $owner = null): AtomicLock
    {
        return new AtomicLock($this, $name, $seconds, $owner);
    }

    /**
     * Restore a lock instance from the given owner.
     *
     * @param string $name
     * @param string|null $owner
     * @return \Phaseolies\Cache\Lock\AtomicLock
     */
    public function restoreLock(string $name, string $owner): AtomicLock
    {
        $lockData = $this->get($name);
        $seconds = 10;

        if ($lockData) {
            $data = json_decode($lockData, true);
            if (is_array($data)) {
                $seconds = $data['duration'] ?? 10;

                $cachedOwner = $data['owner'] ?? '';
                if ($cachedOwner !== $owner) {
                    throw new \RuntimeException("Lock owner mismatch. Expected: {$owner}, Found: {$cachedOwner}");
                }

                // Create the lock as restored
                // it will validate ownership automatically
                return new AtomicLock($this, $name, $seconds, $owner, true);
            }
        }

        // If no lock data exists or data is invalid, create a new lock
        return new AtomicLock($this, $name, $seconds, $owner);
    }
}
