<?php

namespace Phaseolies\Database\Entity\Casts\Handlers;

use Phaseolies\Database\Entity\Casts\Contracts\CastableInterface;

class ObjectCast implements CastableInterface
{
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

    public function set(mixed $value): mixed
    {
        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
