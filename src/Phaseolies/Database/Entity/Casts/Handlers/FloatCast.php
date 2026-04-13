<?php

namespace Phaseolies\Database\Entity\Casts\Handlers;

use Phaseolies\Database\Entity\Casts\Contracts\CastableInterface;

class FloatCast implements CastableInterface
{
    public function get(mixed $value): mixed
    {
        return (float) $value;
    }

    public function set(mixed $value): mixed
    {
        return (float) $value;
    }
}
