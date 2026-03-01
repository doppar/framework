<?php

namespace Tests\Application\Mock\Services;

use Phaseolies\DI\Attributes\Immutable;

#[Immutable]
class MockImmutableWithoutTrait
{
    // Has #[Immutable] but is MISSING the trait — should produce a helpful RuntimeException
    public string $name = 'test';
}