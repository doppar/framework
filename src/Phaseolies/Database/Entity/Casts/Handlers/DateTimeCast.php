<?php

namespace Phaseolies\Database\Entity\Casts\Handlers;

use Carbon\Carbon;
use DateTimeInterface;
use Phaseolies\Database\Entity\Casts\Contracts\CastableInterface;

class DateTimeCast implements CastableInterface
{
    /**
     * @param string $format
     */
    public function __construct(
        protected string $format = 'Y-m-d H:i:s'
    ) {}

    /**
     * Get the value as a Carbon instance.
     *
     * @param mixed $value
     * @return Carbon|null
     */
    public function get(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        return Carbon::parse($value);
    }

    /**
     * Set the value as a formatted date string
     *
     * @param mixed $value
     * @return mixed
     */
    public function set(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format($this->format);
        }

        return $value;
    }
}
