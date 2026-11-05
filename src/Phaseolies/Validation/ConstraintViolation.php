<?php

namespace Phaseolies\Validation;

final readonly class ConstraintViolation
{
    /**
     * Creates a validation constraint violation.
     *
     * @param string $property
     * @param string $messageKey
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        public string $property,
        public string $messageKey,
        public array $parameters = [],
    ) {
    }
}
