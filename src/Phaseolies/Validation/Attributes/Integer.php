<?php

namespace Phaseolies\Validation\Attributes;

use Attribute;
use Phaseolies\Validation\Constraint;
use Phaseolies\Validation\ConstraintViolation;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Integer implements Constraint
{
    /**
     * Creates an integer constraint.
     *
     * @param string $messageKey
     */
    public function __construct(
        private readonly string $messageKey = 'int',
    ) {
    }

    /**
     * Validates that a value is an integer.
     *
     * @param mixed $value
     * @param string $property
     * @param array<string, mixed> $payload
     * @return ConstraintViolation|null
     */
    public function validate(mixed $value, string $property, array $payload): ?ConstraintViolation
    {
        $valid = is_int($value) || (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false);

        return $value !== null && !$valid
            ? new ConstraintViolation($property, $this->messageKey)
            : null;
    }
}
