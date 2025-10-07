<?php

namespace Phaseolies\Utilities\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route
{
    public function __construct(
        public string $path,
        public ?string $name = null,
        public array $methods = ['GET']
    ) {
        if (is_string($this->methods)) {
            $this->methods = [$this->methods];
        }
    }
}
