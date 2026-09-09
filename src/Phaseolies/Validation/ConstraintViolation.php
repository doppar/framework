<?php

namespace Phaseolies\Validation;

final readonly class ConstraintViolation
{
    public function __construct(
        public string $property,
        public string $messageKey,
        public array $parameters = [],
    ) {
    }
}
