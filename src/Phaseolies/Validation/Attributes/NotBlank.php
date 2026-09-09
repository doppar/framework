<?php

namespace Phaseolies\Validation\Attributes;

use Attribute;
use Phaseolies\Validation\Constraint;
use Phaseolies\Validation\ConstraintViolation;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class NotBlank implements Constraint
{
    /**
     * Creates a not-blank constraint.
     *
     * @param string $messageKey
     */
    public function __construct(
        private readonly string $messageKey = 'required',
    ) {
    }

    /**
     * Validates that a value is not blank.
     *
     * @param mixed $value
     * @param string $property
     * @param array<string, mixed> $payload
     * @return ConstraintViolation|null
     */
    public function validate(mixed $value, string $property, array $payload): ?ConstraintViolation
    {
        return $value === null || (is_string($value) && trim($value) === '')
            ? new ConstraintViolation($property, $this->messageKey)
            : null;
    }
}
