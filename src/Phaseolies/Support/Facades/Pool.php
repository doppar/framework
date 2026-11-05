<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static array call(string $command, bool $background = true)
 * @method static bool isProcessRunning(int $pid)
 * @method static array getRunningProcesses()
 *
 * @see \Phaseolies\Console\Schedule\SchedulePool
 */
class Pool extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'pool';
    }
}
