<?php

namespace Phaseolies\Cache;

use Psr\SimpleCache\CacheInterface;
use Phaseolies\Cache\RateLimiting\RateLimit;
use Psr\SimpleCache\InvalidArgumentException;

class RateLimiter
{
    /**
     * The cache store implementation.
     *
     * @var CacheInterface
     */
    protected $cache;

    /**
     * Create a new rate limiter instance.
     *
     * @param  CacheInterface  $cache
     * @return void
     */
    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Determine if the given key has been "accessed" too many times.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @param  int  $decaySeconds
     * @return \Phaseolies\Cache\RateLimiting\RateLimit
     * @throws InvalidArgumentException
     */
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): RateLimit
    {
        $timerKey = $key . '_timer';
        $now = time();

        try {
            if (!$this->cache->has($key)) {
                $this->cache->set($key, 1, $decaySeconds);
                $this->cache->set($timerKey, $now + $decaySeconds, $decaySeconds);
                $hits = 1;
            } else {
                $hits = $this->cache->increment($key);
                if ($hits <= $maxAttempts) {
                    $this->cache->set($timerKey, $now + $decaySeconds, $decaySeconds);
                }
            }

            $remaining = max(0, $maxAttempts - $hits);
            $resetAt = $this->cache->get($timerKey);

            return new RateLimit(
                limit: $maxAttempts,
                remaining: $remaining,
                resetAt: $resetAt ?? $now + $decaySeconds,
            );
        } catch (InvalidArgumentException $e) {
            throw $e;
        }
    }

    /**
     * Determine if the given key has been "accessed" too many times.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @return bool
     * @throws InvalidArgumentException
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        try {
            return $this->attempts($key) >= $maxAttempts;
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Get the number of seconds until the "key" is accessible again.
     *
     * @param  string  $key
     * @return int
     * @throws InvalidArgumentException
     */
    public function availableIn(string $key): int
    {
        try {
            $timer = $this->cache->get($key . '_timer');

            if ($timer === null) {
                return 0;
            }

            return max(0, $timer - time());
        } catch (InvalidArgumentException $e) {
            return 0;
        }
    }

    /**
     * Get the expiration timestamp for the rate limit.
     *
     * @param  int  $seconds
     * @return int
     */
    public function availableAt(int $seconds): int
    {
        return time() + $seconds;
    }

    /**
     * Increment the counter for a given key for a given decay time.
     *
     * @param  string  $key
     * @param  int  $decaySeconds
     * @return int
     * @throws InvalidArgumentException
     */
    public function hit(string $key, int $decaySeconds): int
    {
        $timerKey = $key . '_timer';
        $now = time();

        try {
            if (!$this->cache->has($key)) {
                $this->cache->set($key, 1, $decaySeconds);
                $this->cache->set($timerKey, $now + $decaySeconds, $decaySeconds);
                return 1;
            } else {
                $hits = $this->cache->increment($key);
                $this->cache->set($timerKey, $now + $decaySeconds, $decaySeconds);
                return $hits;
            }
        } catch (InvalidArgumentException $e) {
            throw $e;
        }
    }

    /**
     * Clear the hits and lockout timer for the given key.
     *
     * @param  string  $key
     * @return void
     * @throws InvalidArgumentException
     */
    public function clear(string $key): void
    {
        try {
            $this->cache->delete($key);
            $this->cache->delete($key . '_timer');
        } catch (InvalidArgumentException $e) {
            throw $e;
        }
    }

    /**
     * Get the number of attempts for the given key.
     *
     * @param  string  $key
     * @return int
     * @throws InvalidArgumentException
     */
    public function attempts(string $key): int
    {
        try {
            return (int) ($this->cache->get($key) ?? 0);
        } catch (InvalidArgumentException $e) {
            return 0;
        }
    }

    /**
     * Reset the number of attempts for the given key.
     *
     * @param  string  $key
     * @return void
     * @throws InvalidArgumentException
     */
    public function resetAttempts(string $key): void
    {
        try {
            $this->cache->delete($key);
        } catch (InvalidArgumentException $e) {
            throw $e;
        }
    }
}
