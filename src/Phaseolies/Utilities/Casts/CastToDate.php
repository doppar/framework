<?php

namespace Phaseolies\Utilities\Casts;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class CastToDate
{
    public function __construct(
        public string $format = 'Y-m-d'
    ) {}
}
