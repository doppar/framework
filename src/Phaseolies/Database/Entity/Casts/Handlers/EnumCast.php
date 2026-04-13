<?php

namespace Phaseolies\Database\Entity\Casts\Handlers;

use Phaseolies\Database\Entity\Casts\Contracts\CastableInterface;

class EnumCast implements CastableInterface
{
    /**
     * @param string $enumClass
     */
    public function __construct(
        protected string $enumClass
    ) {}

    public function get(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof $this->enumClass) {
            return $value;
        }

        // Backed enum — from(value)
        if (is_subclass_of($this->enumClass, \BackedEnum::class)) {
            return ($this->enumClass)::from($value);
        }

        // Pure enum — match by name
        foreach (($this->enumClass)::cases() as $case) {
            if ($case->name === $value) {
                return $case;
            }
        }

        throw new \ValueError(
            "Value [{$value}] is not a valid case for enum [{$this->enumClass}]"
        );
    }

    public function set(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        return $value;
    }
}
