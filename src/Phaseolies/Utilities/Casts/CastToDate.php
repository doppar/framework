<?php

namespace Phaseolies\Utilities\Casts;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class CastToDate
{
    public function __construct(
        public string $format = 'Y-m-d'
    ) {}
}
