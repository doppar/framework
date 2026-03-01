<?php

namespace Phaseolies\DI\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Immutable
{
    public function __construct() {}
}
