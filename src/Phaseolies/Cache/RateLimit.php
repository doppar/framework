<?php

namespace Phaseolies\Cache\RateLimiting;

class RateLimit
{
    /**
     * Create a new rate limit instance.
     *
     * @param  int  $limit
     * @param  int  $remaining
     * @param  int  $resetAt
     * @return void
     */
    public function __construct(
        public readonly int $limit,
        public readonly int $remaining,
        public readonly int $resetAt,
    ) {}
}
