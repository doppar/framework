<?php

namespace Tests\Support\Watches;

use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Entity\Watches\WatchConditionInterface;

/**
 * A condition that always returns true.
 * Used to verify that watches fire when a class-based condition passes.
 */
class PassCondition implements WatchConditionInterface
{
    public function evaluate(mixed $old, mixed $new, Model $model): bool
    {
        return true;
    }
}
