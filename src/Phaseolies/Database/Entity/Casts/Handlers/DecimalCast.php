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

    /**
     * Get the value as a decimal.
     *
     * @param mixed $value
     * @return float|null
     */
    public function get(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, $this->precision);
    }

    /**
     * Set the value as a decimal.
     *
     * @param mixed $value
     * @return float|null
     */
    public function set(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, $this->precision);
    }
}
