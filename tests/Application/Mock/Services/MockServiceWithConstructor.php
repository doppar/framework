<?php

namespace Tests\Application\Mock\Services;

use Phaseolies\DI\Attributes\Immutable;
use Phaseolies\DI\Concerns\EnforcesImmutability;

#[Immutable]
class MockServiceWithConstructor
{
    use EnforcesImmutability;

    public string $name;
    public int $value;

    public function __construct(string $name = 'default', int $value = 0)
    {
        $this->name = $name;
        $this->value = $value;
    }
}
