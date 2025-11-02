<?php

namespace Phaseolies\Utilities\Attributes;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Model
{
    public function __construct(
        public readonly ?string $column = 'id',
        public readonly bool $exception = false,
    ) {}
}
