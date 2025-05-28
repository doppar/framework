<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Console\Schedule\SchedulePool call(string $command, bool $background = true): array
 * @method static \Phaseolies\Console\Schedule\SchedulePool isProcessRunning(int $pid): bool
 * @method static \Phaseolies\Console\Schedule\SchedulePool getRunningProcesses(): array
 * @see \Phaseolies\Console\Schedule\SchedulePool
 */

use Phaseolies\Facade\BaseFacade;

class Pool extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'pool';
    }
}
