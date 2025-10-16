<?php

namespace Phaseolies\Utilities\Attributes;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class BindPayload
{
    public function __construct(
        public bool $strict = true,
    ) {}
}
