<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Support\Facades\Route;

class RouteCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'route:cache';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Cache the application routes';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return $this->executeWithTiming(function() {
            $cacheDir = base_path('storage/framework/cache');

            if (!is_dir($cacheDir)) {
                $this->displayError('Cache directory does not exist:');
                $this->line('<fg=white>' . $cacheDir . '</>');
                return 1;
            }

            $this->line('<fg=yellow>⏳ Caching routes...</>');
            Route::cacheRoutes();

            $this->displaySuccess('Routes cached successfully');
            return 0;
        });
    }
}
