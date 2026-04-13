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
