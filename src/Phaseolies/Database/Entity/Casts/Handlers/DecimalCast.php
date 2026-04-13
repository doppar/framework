<?php

namespace Phaseolies\Database\Entity\Casts\Handlers;

use Phaseolies\Database\Entity\Casts\Contracts\CastableInterface;

class DecimalCast implements CastableInterface
{
    /**
     * @param int $precision
     */
    public function __construct(
        protected int $precision = 2
    ) {}

    public function get(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, $this->precision);
    }

    public function set(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, $this->precision);
    }
}
