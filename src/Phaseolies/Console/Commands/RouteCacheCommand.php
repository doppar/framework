<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Support\Facades\Route;
use RuntimeException;

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
        $startTime = microtime(true);
        $this->newLine();

        try {
            $cacheDir = base_path('storage/framework/cache');

            if (!is_dir($cacheDir)) {
                $this->line('<bg=red;options=bold> ERROR </> Cache directory does not exist:');
                $this->newLine();
                $this->line('<fg=white>' . $cacheDir . '</>');
                $this->newLine();
                return 1;
            }

            $this->line('<fg=yellow>⏳ Caching routes...</>');
            Route::cacheRoutes();

            $executionTime = microtime(true) - $startTime;
            $this->newLine();
            $this->line('<bg=green;options=bold> SUCCESS </> Routes cached successfully');
            $this->newLine();
            $this->line(sprintf(
                "<fg=yellow>⏱ Time:</> <fg=white>%.4fs</> <fg=#6C7280>(%d μs)</>",
                $executionTime,
                (int) ($executionTime * 1000000)
            ));
            $this->newLine();

            return 0;
        } catch (RuntimeException $e) {
            $this->line('<bg=red;options=bold> ERROR </> ' . $e->getMessage());
            $this->newLine();
            $this->line('<fg=red>✖ ERROR: ' . $e->getMessage() . '</>');
            $this->newLine();
            return 1;
        }
    }
}
