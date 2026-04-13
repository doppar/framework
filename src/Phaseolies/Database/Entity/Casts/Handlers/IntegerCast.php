<?php

namespace Phaseolies\Database\Entity\Casts\Handlers;

use Phaseolies\Database\Entity\Casts\Contracts\CastableInterface;

class IntegerCast implements CastableInterface
{
    /**
     * Cast the given value to an integer.
     *
     * @param mixed $value
     * @return int
     */
    public function get(mixed $value): mixed
    {
        return (int) $value;
    }

    /**
     * Cast the given value to an integer before saving to the database.
     *
     * @param mixed $value
     * @return int
     */
    public function set(mixed $value): mixed
    {
        return (int) $value;
    }
}
