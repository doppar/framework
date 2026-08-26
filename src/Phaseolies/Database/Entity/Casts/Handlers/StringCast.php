<?php

namespace Phaseolies\Database\Entity\Casts\Handlers;

use Phaseolies\Database\Entity\Casts\Contracts\CastableInterface;

class StringCast implements CastableInterface
{
    /**
     * Cast the given value to a string.
     *
     * @param mixed $value
     * @return string
     */
    public function get(mixed $value): mixed
    {
        return (string) $value;
    }

    /**
     * Cast the given value to a string before saving to the database.
     *
     * @param mixed $value
     * @return string
     */
    public function set(mixed $value): mixed
    {
        return (string) $value;
    }
}
