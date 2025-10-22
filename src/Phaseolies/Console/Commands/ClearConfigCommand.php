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
        return $this->withTiming(function () {
            app('config')->clearCache();
            return Command::SUCCESS;
        }, 'Configuration cache has been gracefully cleared.');
    }
}
