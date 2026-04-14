<?php

namespace Tests\Support\Watches;

/**
 * A plain class that does NOT implement WatchConditionInterface.
 * Used to verify that WatchesHandler throws a RuntimeException when
 * the 'when' class is registered but does not implement the interface.
 */
class NotAConditionClass
{
    public function evaluate(): bool
    {
        return true;
    }
}
