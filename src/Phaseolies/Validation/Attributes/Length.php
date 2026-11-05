<?php

namespace Phaseolies\Validation\Attributes;

use Attribute;
use Phaseolies\Validation\Constraint;
use Phaseolies\Validation\ConstraintViolation;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Length implements Constraint
{
    /**
     * Creates a length constraint.
     *
     * @param int|null $min
     * @param int|null $max
     */
    public function __construct(
        private readonly ?int $min = null,
        private readonly ?int $max = null,
    ) {
    }

    /**
     * Validates the length of a string value.
     *
     * @param mixed $value
     * @param string $property
     * @param array<string, mixed> $payload
     * @return ConstraintViolation|null
     */
    public function validate(mixed $value, string $property, array $payload): ?ConstraintViolation
    {
        if ($value === null || !is_string($value)) {
            return null;
        }

        $length = mb_strlen($value);

        if ($this->min !== null && $length < $this->min) {
            return new ConstraintViolation($property, 'min.string', [
                ':min' => $this->min,
                'min' => $this->min,
            ]);
        }

        if ($this->max !== null && $length > $this->max) {
            return new ConstraintViolation($property, 'max.string', [
                ':max' => $this->max,
                'max' => $this->max,
            ]);
        }

        return null;
    }
}
