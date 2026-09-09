<?php

namespace Phaseolies\Validation\Attributes;

use Attribute;
use Phaseolies\Validation\Constraint;
use Phaseolies\Validation\ConstraintViolation;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Integer implements Constraint
{
    public function __construct(
        private readonly string $messageKey = 'int',
    ) {
    }

    public function validate(mixed $value, string $property, array $payload): ?ConstraintViolation
    {
        $valid = is_int($value) || (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false);

        return $value !== null && !$valid
            ? new ConstraintViolation($property, $this->messageKey)
            : null;
    }
}
