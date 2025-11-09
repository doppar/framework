<?php

namespace Tests\Unit\Hooks;

use Phaseolies\Database\Entity\Model;

class AfterUpdatedHook
{
    public function handle(Model $model): void
    {
        $model::$wasCalledAfterUpdated = true;
    }
}
