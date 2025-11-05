<?php

namespace Phaseolies\Utilities\Attributes;

use Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Mapper
{
    public function __construct(
        public readonly ?string $prefix = null,
        public readonly ?array $middleware = null,
    ) {}
}
