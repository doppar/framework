<?php

namespace Phaseolies\Cache;

use DateTime;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Phaseolies\Cache\Lock\AtomicLock;
use Symfony\Contracts\Cache\CacheInterface as ContractsCacheInterface;

class CacheStore implements IncrementableCacheInterface
{
    /**
     * The cache adapter instance.
     *
     * @var AdapterInterface
     */
    protected AdapterInterface $adapter;

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
        $this->prefix = (string) ($prefix ?? config('caching.prefix'));
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
     * Validate a cache key and return the prefixed adapter key.
     *
     * @param mixed $key
     * @return string
     */
    protected function prefixedValidatedKey($key): string
    {
        $key = $this->normalizeKey($key);

        return $this->prefixedKey($key);
    }

    /**
     * Get the cache data by key
     *
     * @param mixed $key
     * @param null $default
     * @return mixed
     */
    #[\Override]
    public function get($key, $default = null): mixed
    {
        $key = $this->prefixedValidatedKey($key);
        $item = $this->adapter->getItem($key);

        return $item->isHit() ? $item->get() : $default;
    }

    /**
     * Stores a value in the cache with a specified key.
     *
     * @param mixed $key
     * @param mixed $value
     * @param null $ttl
     * @return bool
     */
    #[\Override]
    public function set($key, $value, $ttl = null): bool
    {
        $key = $this->prefixedValidatedKey($key);

        $item = $this->adapter->getItem($key);
        $item->set($value);

        if ($ttl !== null) {
            $item->expiresAfter($this->convertTtlToSeconds($ttl));
        }

        return $this->adapter->save($item);
    }

    /**
     * Deletes a value from the cache by its key.
     *
     * @param mixed $key
     * @return bool
     */
    #[\Override]
    public function delete($key): bool
    {
        $key = $this->prefixedValidatedKey($key);

        return $this->adapter->deleteItem($key);
    }

    /**
     * Clears all items from the cache.
     *
     * @return bool
     */
    #[\Override]
    public function clear(): bool
    {
        return $this->adapter->clear($this->prefix);
    }

    /**
     * Retrieves multiple cache items by their keys.
     *
     * @param iterable $keys
     * @param mixed $default
     * @return iterable
     */
    #[\Override]
    public function getMultiple($keys, $default = null): iterable
    {
        $keys = $this->normalizeKeyList($keys);
        $prefixedKeys = [];

        foreach ($keys as $key) {
            $prefixedKeys[] = $this->prefixedValidatedKey($key);
        }

        $items = $this->adapter->getItems($prefixedKeys);
        $results = [];

        foreach ($items as $key => $item) {
            $originalKey = substr($key, strlen($this->prefix));
            $results[$originalKey] = $item->isHit() ? $item->get() : $default;
        }

        foreach ($keys as $key) {
            $results[$key] ??= $default;
        }

        return $results;
    }

    /**
     * Stores multiple cache items at once.
     *
     * @param iterable $values
     * @param null|int|\DateInterval $ttl
     * @return bool
     */
    #[\Override]
    public function setMultiple($values, $ttl = null): bool
    {
        $values = $this->normalizeValueMap($values);

        $success = true;
        $ttl = $this->convertTtlToSeconds($ttl);

        foreach ($values as $key => $value) {
            $prefixedKey = $this->prefixedValidatedKey($key);
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
     * Deletes multiple cache items by their keys.
     *
     * @param iterable $keys
     * @return bool
     */
    #[\Override]
    public function deleteMultiple($keys): bool
    {
        $keys = $this->normalizeKeyList($keys);
        $prefixedKeys = [];

        foreach ($keys as $key) {
            $prefixedKeys[] = $this->prefixedValidatedKey($key);
        }

        return $this->adapter->deleteItems($prefixedKeys);
    }

    /**
     * Checks whether a cache item exists for the given key.
     *
     * @param mixed $key
     * @return bool
     */
    #[\Override]
    public function has($key): bool
    {
        $key = $this->prefixedValidatedKey($key);

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
        $key = $this->prefixedValidatedKey($key);

        return $this->withKeyLock($key, function () use ($key, $value) {
            $item = $this->adapter->getItem($key);

            if (!$item->isHit()) {
                return false;
            }

            $current = (int) $item->get();
            $new = $current + $value;
            $item->set($new);

            if ($item->getMetadata()['expiry'] ?? null) {
                $item->expiresAt(DateTime::createFromFormat('U', (string) $item->getMetadata()['expiry']));
            }

            return $this->adapter->save($item) ? $new : false;
        });
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
        $key = $this->prefixedValidatedKey($key);

        return $this->withKeyLock($key, function () use ($key, $value) {
            $item = $this->adapter->getItem($key);

            if (!$item->isHit()) {
                return false;
            }

            $current = (int) $item->get();
            $new = $current - $value;
            $item->set($new);

            if ($item->getMetadata()['expiry'] ?? null) {
                $item->expiresAt(DateTime::createFromFormat('U', (string) $item->getMetadata()['expiry']));
            }

            return $this->adapter->save($item) ? $new : false;
        });
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
        $key = $this->prefixedValidatedKey($key);
        $seconds = $this->convertTtlToSeconds($ttl);

        if ($this->adapter instanceof ContractsCacheInterface) {
            $created = false;

            $this->adapter->get($key, function ($item) use (&$created, $value, $seconds) {
                $created = true;

                if ($seconds !== null) {
                    $item->expiresAfter($seconds);
                }

                return $value;
            });

            return $created;
        }

        return $this->withKeyLock($key, function () use ($key, $value, $seconds) {
            if ($this->adapter->hasItem($key)) {
                return false;
            }

            $item = $this->adapter->getItem($key);
            $item->set($value);

            if ($seconds !== null) {
                $item->expiresAfter($seconds);
            }

            return $this->adapter->save($item);
        });
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
        $key = $this->prefixedValidatedKey($key);

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
        $key = $this->prefixedValidatedKey($key);

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

        if ($key === '') {
            throw new \InvalidArgumentException('Cache key must not be empty');
        }

        if (preg_match('/[{}()\/\\\\@\:]/', $key)) {
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
     * @param null|int|\DateInterval $ttl
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
     * Normalize an individual cache key.
     *
     * @param mixed $key
     * @return string
     */
    protected function normalizeKey($key): string
    {
        $this->validateKey($key);

        return $key;
    }

    /**
     * Normalize an iterable of cache keys into a sequential array of strings.
     *
     * @param mixed $keys
     * @return array<int, string>
     */
    protected function normalizeKeyList($keys): array
    {
        if ($keys instanceof \Traversable) {
            $keys = iterator_to_array($keys, false);
        } elseif (!is_array($keys)) {
            throw new \InvalidArgumentException('Keys must be an array or traversable');
        }

        $normalized = [];

        foreach ($keys as $key) {
            $normalized[] = $this->normalizeKey($key);
        }

        return $normalized;
    }

    /**
     * Normalize an iterable of key/value pairs into an array.
     *
     * @param mixed $values
     * @return array<string, mixed>
     */
    protected function normalizeValueMap($values): array
    {
        if ($values instanceof \Traversable) {
            $values = iterator_to_array($values, true);
        } elseif (!is_array($values)) {
            throw new \InvalidArgumentException('Values must be an array or traversable');
        }

        $normalized = [];

        foreach ($values as $key => $value) {
            $normalized[$this->normalizeKey($key)] = $value;
        }

        return $normalized;
    }

    /**
     * Execute a callback while holding a process-local lock for the key.
     *
     * @template T
     * @param string $key
     * @param \Closure(): T $callback
     * @return T
     */
    protected function withKeyLock(string $key, \Closure $callback): mixed
    {
        $directory = $this->lockDirectory();

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory . DIRECTORY_SEPARATOR . sha1($key) . '.lock';
        $handle = fopen($path, 'c+');

        if ($handle === false) {
            throw new \RuntimeException(sprintf('Unable to open cache lock file: %s', $path));
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException(sprintf('Unable to acquire cache lock: %s', $path));
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Resolve the directory used for process-local cache locks.
     *
     * @return string
     */
    protected function lockDirectory(): string
    {
        if (function_exists('storage_path')) {
            try {
                return storage_path('framework/cache/locks');
            } catch (\Throwable) {
                // Fall back to the system temp directory when the application container
                // is not fully bootstrapped, e.g. in isolated unit tests.
            }
        }

        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'doppar-cache-locks';
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
        if ($this->has($key)) {
            return $this->get($key);
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
        if ($this->has($key)) {
            return $this->get($key);
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
     * @param bool $condition
     * @param int|\DateInterval|null $ttl
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
