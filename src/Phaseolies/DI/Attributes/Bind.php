<?php

namespace Phaseolies\DI\Attributes;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Bind
{
    public function __construct(
        public string $concrete,
        public bool $singleton = false
    ) {}
}
