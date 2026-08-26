<?php

namespace Phaseolies\Database\Entity\Casts\Handlers;

use Phaseolies\Database\Entity\Casts\Contracts\CastableInterface;

class BooleanCast implements CastableInterface
{
    /**
     * Get the value as a boolean.
     *
     * @param mixed $value
     * @return bool
     */
    public function get(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    /**
     * Set the value as a boolean integer (0 or 1)
     *
     * @param mixed $value
     * @return mixed
     */
    public function set(mixed $value): mixed
    {
        return (int) (bool) $value;
    }
}
