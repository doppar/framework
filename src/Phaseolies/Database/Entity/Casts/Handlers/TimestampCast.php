<?php

namespace Phaseolies\Database\Entity\Casts\Handlers;

use Carbon\Carbon;
use DateTimeInterface;
use Phaseolies\Database\Entity\Casts\Contracts\CastableInterface;

class TimestampCast implements CastableInterface
{
    /**
     * Get the value from the database and convert it to a Carbon instance.
     *
     * @param mixed $value
     * @return Carbon|null
     */
    public function get(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
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
     * Set the value for the database and convert it to a timestamp.
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
            return $value->getTimestamp();
        }

        return $value;
    }
}
