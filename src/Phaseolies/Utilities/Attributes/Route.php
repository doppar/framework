<?php

namespace Phaseolies\Utilities\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Route
{
    public function __construct(
        public string $uri,
        public array $methods = ['GET'],
        public ?string $name = null,
        public array $middleware = [],
        public ?int $rateLimit = null,
        public ?int $rateLimitDecay = 1
    ) {
        if (is_string($this->methods)) {
            $this->methods = [$this->methods];
        }
    }
}
