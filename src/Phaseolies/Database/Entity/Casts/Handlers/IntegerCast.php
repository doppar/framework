<?php

namespace Phaseolies\Database\Entity\Casts\Handlers;

use Phaseolies\Database\Entity\Casts\Contracts\CastableInterface;

class IntegerCast implements CastableInterface
{
    public function get(mixed $value): mixed
    {
        return (int) $value;
    }

    public function set(mixed $value): mixed
    {
        return (int) $value;
    }
}
