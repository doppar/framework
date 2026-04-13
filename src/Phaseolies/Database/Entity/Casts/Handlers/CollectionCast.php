<?php

namespace Phaseolies\Database\Entity\Casts\Handlers;

use Phaseolies\Database\Entity\Casts\Contracts\CastableInterface;
use Phaseolies\Support\Collection;

class CollectionCast implements CastableInterface
{
    /**
     * Get the value as a Collection
     *
     * @param mixed $value
     * @return Collection
     */
    public function get(mixed $value): mixed
    {
        if ($value instanceof Collection) {
            return $value;
        }

        if (is_array($value)) {
            return new Collection('array', $value);
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            $items = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
            return new Collection('array', $items);
        }

        return new Collection('array', []);
    }

    /**
     * Set the value as a JSON string
     *
     * @param mixed $value
     * @return mixed
     */
    public function set(mixed $value): mixed
    {
        if ($value instanceof Collection) {
            return json_encode($value->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_string($value)) {
            return $value;
        }

        return json_encode($value);
    }
}
