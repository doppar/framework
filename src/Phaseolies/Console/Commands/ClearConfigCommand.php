<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;

class ClearConfigCommand extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'config:clear';

    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $description = 'Clear all config cache data.)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        $startTime = microtime(true);

        $this->newLine();

        app('config')->clearCache();

        $executionTime = microtime(true) - $startTime;

        $this->newLine();
        $this->line('<bg=green;options=bold> SUCCESS </> Configuration cache has been gracefully cleared.');
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
