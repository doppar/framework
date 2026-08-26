<?php

namespace Tests\Application\Mock\Services;

use Phaseolies\DI\Attributes\Immutable;
use Phaseolies\DI\Concerns\EnforcesImmutability;

#[Immutable]
class MockMailerService
{
    use EnforcesImmutability;

    public string $host = 'smtp.example.com';
    public int $port = 587;
}
