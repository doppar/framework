<?php

namespace Phaseolies\Utilities\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Resolver
{
    public function __construct(
        public string $abstract,
        public string $concrete,
        public bool $singleton = false
    ) {}
}
