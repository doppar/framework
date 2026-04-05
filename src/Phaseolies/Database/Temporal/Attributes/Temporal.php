<?php

namespace Phaseolies\Database\Temporal\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Temporal
{
    /**
     * @param string $suffix
     * @param bool $trackActor
     */
    public function __construct(
        public readonly string $suffix = '_history',
        public readonly bool $trackActor = false,
    ) {}
}
