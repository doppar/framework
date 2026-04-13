<?php

namespace Phaseolies\Database\Entity\Casts\Handlers;

use Phaseolies\Database\Entity\Casts\Contracts\CastableInterface;

class StringCast implements CastableInterface
{
    public function get(mixed $value): mixed
    {
        return (string) $value;
    }

    public function set(mixed $value): mixed
    {
        return (string) $value;
    }
}
