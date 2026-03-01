<?php

namespace Phaseolies\DI\Exceptions;

use RuntimeException;

class ImmutableViolationException extends RuntimeException
{
    public function __construct(string $class, string $property)
    {
        parent::__construct(
            "Cannot mutate property \${$property} on immutable service [{$class}]. " .
                "Services marked #[Immutable] are frozen after instantiation."
        );
    }
}
