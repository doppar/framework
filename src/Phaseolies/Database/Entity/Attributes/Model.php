<?php

namespace Phaseolies\Database\Entity\Attributes;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Model
{
    public function __construct(
        public readonly ?string $column = null,
        public readonly bool $exception = false,
    ) {}
}
