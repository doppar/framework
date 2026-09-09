<?php

namespace Phaseolies\Validation;

interface Constraint
{
    public function validate(mixed $value, string $property, array $payload): ?ConstraintViolation;
}
