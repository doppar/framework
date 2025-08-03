<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Support\Facades\Config;

class ConfigCacheCommand extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'config:cache';

    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $description = 'Cache the configuration files.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        $startTime = microtime(true);

        $this->newLine();

        Config::clearCache();
        Config::loadAll();
        Config::cacheConfig();

        $executionTime = microtime(true) - $startTime;

        $this->newLine();
        $this->line('<bg=green;options=bold> SUCCESS </> Application configuration cached successfully.');
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
