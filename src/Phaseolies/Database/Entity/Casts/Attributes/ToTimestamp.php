<?php

namespace Phaseolies\Database\Entity\Casts\Attributes;

use Attribute;
use Phaseolies\Database\Entity\Casts\Type;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ToTimestamp extends Transform
{
    public function __construct()
    {
        parent::__construct(Type::Timestamp);
    }
}
