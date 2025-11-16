<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Config\Config;
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
        return $this->withTiming(function() {
            Config::clearCache();
            Config::loadAll();
            Config::cacheConfig();
            return Command::SUCCESS;
        }, 'Application configuration cached successfully.');
    }
}
