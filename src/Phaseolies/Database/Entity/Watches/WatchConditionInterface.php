<?php

namespace Phaseolies\Database\Entity\Watches;

use Phaseolies\Database\Entity\Model;

interface WatchConditionInterface
{
    /**
     * Evaluate whether the watch watcher should fire.
     *
     * @param mixed $old 
     * @param mixed $new
     * @param Model $model
     * @return bool
     */
    public function evaluate(mixed $old, mixed $new, Model $model): bool;
}
