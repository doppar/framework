<?php

namespace Phaseolies\Validation\Attributes;

use Attribute;
use Phaseolies\Validation\Constraint;
use Phaseolies\Validation\ConstraintViolation;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Between implements Constraint
{
    /**
     * Creates a between constraint.
     *
     * @param int|float $min
     * @param int|float $max
     */
    public function __construct(
        private readonly int|float $min,
        private readonly int|float $max,
    ) {
    }

    /**
     * Validates that a value is within the configured range.
     *
     * @param mixed $value
     * @param string $property
     * @param array<string, mixed> $payload
     * @return ConstraintViolation|null
     */
    public function validate(mixed $value, string $property, array $payload): ?ConstraintViolation
    {
        if ($value === null || !is_numeric($value)) {
            return null;
        }

        return $value < $this->min || $value > $this->max
            ? new ConstraintViolation($property, 'between', [
                ':min' => $this->min,
                'min' => $this->min,
                ':max' => $this->max,
                'max' => $this->max,
            ])
            : null;
    }
}
