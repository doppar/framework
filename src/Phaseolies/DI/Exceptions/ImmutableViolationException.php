<?php

namespace Phaseolies\DI\Exceptions;

use RuntimeException;

class ImmutableViolationException extends RuntimeException
{
    public function __construct(string $class, string $property)
    {
        if ($property === '__clone') {
            parent::__construct(
                "Cannot clone immutable service [{$class}]. " .
                    "Services marked #[Immutable] are frozen once the container resolves them, " .
                    "and a clone would be a way around that. Resolve the service from the container instead."
            );

            return;
        }

        parent::__construct(
            "Cannot mutate property \${$property} on immutable service [{$class}]. " .
                "Services marked #[Immutable] are frozen once the container resolves them. " .
                "Configure the service in a launcher during boot, before it is first resolved, not at runtime."
        );
    }
}
