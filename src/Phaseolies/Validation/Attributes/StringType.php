<?php

namespace Phaseolies\Validation\Attributes;

use Attribute;
use Phaseolies\Validation\Constraint;
use Phaseolies\Validation\ConstraintViolation;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class StringType implements Constraint
{
    /**
     * Creates a string constraint.
     *
     * @param string $messageKey
     */
    public function __construct(
        private readonly string $messageKey = 'string',
    ) {
    }

    /**
     * Validates that a value is a string.
     *
     * @param mixed $value
     * @param string $property
     * @param array<string, mixed> $payload
     * @return ConstraintViolation|null
     */
    public function validate(mixed $value, string $property, array $payload): ?ConstraintViolation
    {
        return $value !== null && !is_string($value)
            ? new ConstraintViolation($property, $this->messageKey)
            : null;
    }
}
