<?php

namespace Phaseolies\Support\Router\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Throttle
{
    /**
     * Rate limit configuration
     *
     * @param int $maxAttempts Maximum requests allowed
     * @param int $decayMinutes Time window in minutes
     */
    public function __construct(
        public int $maxAttempts = 60,
        public int $decayMinutes = 1
    ) {}
}
