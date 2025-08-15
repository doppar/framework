<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Support\Facades\Route;
use Phaseolies\Console\Schedule\Command;
use Phaseolies\Config\Config;

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
        return $this->withTiming(function() {
            Route::clearRouteCache();
            Config::clearCache();
            Config::loadAll();
            Config::cacheConfig();
            return 0;
        }, 'Route cache has been gracefully cleared.');
    }
}
