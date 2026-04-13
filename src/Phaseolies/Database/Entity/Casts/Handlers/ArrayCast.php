<?php

namespace Phaseolies\Database\Entity\Casts\Handlers;

use Phaseolies\Database\Entity\Casts\Contracts\CastableInterface;

class ArrayCast implements CastableInterface
{
    /**
     * Get the value as an array.
     *
     * @param mixed $value
     * @return array
     */
    public function get(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        return [];
    }

    /**
     * Set the value as a JSON string
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
