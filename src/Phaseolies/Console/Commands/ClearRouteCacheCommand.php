<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Support\Facades\Route;
use Phaseolies\Console\Schedule\Command;

class ClearRouteCacheCommand extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'route:clear';

    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $description = 'Clear all route cache files from the storage/framework/cache folder';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        $startTime = microtime(true);

        $this->newLine();

        Route::clearRouteCache();

        $executionTime = microtime(true) - $startTime;

        $this->newLine();
        $this->line('<bg=green;options=bold> SUCCESS </> Route cache has been gracefully cleared.');
        $this->newLine();
        $this->line(sprintf(
            "<fg=yellow>⏱ Time:</> <fg=white>%.4fs</> <fg=#6C7280>(%d μs)</>",
            $executionTime,
            (int) ($executionTime * 1000000)
        ));
        $this->newLine();
        return 0;
    }
}
