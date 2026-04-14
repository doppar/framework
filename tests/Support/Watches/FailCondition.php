<?php

namespace Tests\Support\Watches;

use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Entity\Watches\WatchConditionInterface;

/**
 * A condition that always returns false.
 * Used to verify that watches are skipped when a class-based condition fails.
 */
class FailCondition implements WatchConditionInterface
{
    public function evaluate(mixed $old, mixed $new, Model $model): bool
    {
        return false;
    }
}
