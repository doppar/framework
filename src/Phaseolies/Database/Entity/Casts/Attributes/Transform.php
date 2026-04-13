<?php

namespace Phaseolies\Database\Entity\Casts\Attributes;

use Attribute;
use Phaseolies\Database\Entity\Casts\Type;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Transform
{
    /**
     * The resolved cast type string.
     *
     * @var string
     */
    public readonly string $type;

    /**
     * @param Type|string $type
     */
    public function __construct(Type|string $type)
    {
        $this->type = $type instanceof Type ? $type->value : $type;
    }
}
