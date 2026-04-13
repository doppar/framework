<?php

namespace Phaseolies\Database\Entity\Casts\Handlers;

use Carbon\Carbon;
use DateTimeInterface;
use Phaseolies\Database\Entity\Casts\Contracts\CastableInterface;

class DateCast implements CastableInterface
{
    /**
     * Get the value as a Carbon instance at the start of the day
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
            return $value->startOfDay();
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        return Carbon::parse($value)->startOfDay();
    }

    /**
     * Set the value as a date string (Y-m-d)
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
            return $value->format('Y-m-d');
        }

        return $value;
    }
}
