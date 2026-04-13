<?php

namespace Phaseolies\Database\Entity\Casts\Attributes;

use Attribute;
use Phaseolies\Database\Entity\Casts\Type;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ToDateTime extends Transform
{
    public function __construct()
    {
        parent::__construct(Type::DateTime);
    }
}
