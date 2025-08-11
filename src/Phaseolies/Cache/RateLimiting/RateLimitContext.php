<?php

namespace Phaseolies\Cache\RateLimiting;

class RateLimitContext
{
    /**
     * Create a new rate limit context instance.
     *
     * @param string $key
     * @param int $maxAttempts
     * @param int $decaySeconds
     * @return void
     */
    public function __construct(
        public readonly string $key,
        public readonly int $maxAttempts,
        public readonly int $decaySeconds,
    ) {}
}
