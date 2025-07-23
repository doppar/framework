<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Support\Facades\Pool;
use Phaseolies\Console\Schedule\Command;

class AppBoostCommand extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'boost';

    /**
     * The description of the console command
     */
    protected $description = 'Optimizes application performance by clearing and rebuilding caches (config, routes, views)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        $commands = [
            'cache:clear',        // Clear application cache
            'route:clear',        // Clear route cache
            'route:cache',        // Rebuild route cache
            'config:clear',       // Clear configuration cache
            'config:cache',       // Rebuild configuration cache
            'view:clear',         // Clear compiled views
            'view:cache',         // Recompile views
        ];

        foreach ($commands as $command) {
            $this->runCommand($command);
        }

        $this->info('Application optimization completed successfully.');

        return 0;
    }

    /**
     * Run a command via Pool and output status.
     *
     * @param string $command
     * @return void
     */
    protected function runCommand(string $command): void
    {
        $this->info("Starting: <comment>{$command}</comment>");

        Pool::call($command, false);

        $this->info("Completed: <info>{$command}</info>");
    }
}
