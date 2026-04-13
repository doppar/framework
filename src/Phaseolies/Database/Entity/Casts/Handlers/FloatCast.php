<?php

namespace Phaseolies\Database\Entity\Casts\Handlers;

use Phaseolies\Database\Entity\Casts\Contracts\CastableInterface;

class FloatCast implements CastableInterface
{
    /**
     * Cast the given value to a float.
     *
     * @param mixed $value
     * @return float
     */
    public function get(mixed $value): mixed
    {
        return (float) $value;
    }

    /**
     * Cast the given value to a float before saving.
     *
     * @param mixed $value
     * @return float
     */
    public function set(mixed $value): mixed
    {
        return (float) $value;
    }
}
