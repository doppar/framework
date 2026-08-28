<?php

namespace Phaseolies\Http\Requests\Attributes;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class BindPayload
{
    public function __construct(
        public bool $strict = false,
    ) {}
}
