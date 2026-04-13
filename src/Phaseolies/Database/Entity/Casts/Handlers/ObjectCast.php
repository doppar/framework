<?php

namespace Phaseolies\Database\Entity\Casts\Handlers;

use Phaseolies\Database\Entity\Casts\Contracts\CastableInterface;

class ObjectCast implements CastableInterface
{
    /**
     * Get the value of the cast.
     *
     * @param mixed $value
     * @return mixed
     */
    public function get(mixed $value): mixed
    {
        if (is_object($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value);
            return $decoded ?? (object) [];
        }

        return (object) [];
    }

    /**
     * Set the value of the cast.
     *
     * @param mixed $value
     * @return mixed
     */
    public function set(mixed $value): mixed
    {
        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
