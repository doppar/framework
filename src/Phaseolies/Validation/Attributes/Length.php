<?php

namespace Phaseolies\Validation\Attributes;

use Attribute;
use Phaseolies\Validation\Constraint;
use Phaseolies\Validation\ConstraintViolation;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Length implements Constraint
{
    public function __construct(
        private readonly ?int $min = null,
        private readonly ?int $max = null,
    ) {
    }

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
