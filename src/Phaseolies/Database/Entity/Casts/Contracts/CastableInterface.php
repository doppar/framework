<?php

namespace Phaseolies\Database\Entity\Casts\Contracts;

interface CastableInterface
{
    /**
     * Cast the raw database value to a PHP type on get.
     *
     * @param mixed $value
     * @return mixed
     */
    public function get(mixed $value): mixed;

    /**
     * Cast the PHP value to a database-compatible format on set.
     *
     * @param mixed $value
     * @return mixed
     */
    public function set(mixed $value): mixed;
}
