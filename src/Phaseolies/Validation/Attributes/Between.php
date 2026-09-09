<?php

namespace Phaseolies\Validation\Attributes;

use Attribute;
use Phaseolies\Validation\Constraint;
use Phaseolies\Validation\ConstraintViolation;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Between implements Constraint
{
    public function __construct(
        private readonly int|float $min,
        private readonly int|float $max,
    ) {
    }

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
