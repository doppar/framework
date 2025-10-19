<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Support\Facades\Pool;
use Phaseolies\Console\Schedule\Command;
use RuntimeException;

class AppBoostCommand extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'boost';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Optimizes application performance by clearing and rebuilding caches';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return $this->executeWithTiming(function() {
            $commands = [
                'cache:clear',        // Clear application cache
                'route:clear',        // Clear route cache
                'route:cache',        // Rebuild route cache
                'config:clear',       // Clear configuration cache
                'config:cache',       // Rebuild configuration cache
                'view:clear',         // Clear compiled views
                'view:cache',         // Recompile views
            ];

            $this->line('<fg=yellow>⚡ Running application optimization commands:</>');
            $this->newLine();

            foreach ($commands as $command) {
                $this->runCommand($command);
            }

            $this->newLine();
            $this->displaySuccess('Application optimization completed successfully');
            return 0;
        });
    }

    /**
     * Run a command via Pool and output status.
     *
     * @param string $command
     * @return void
     * @throws RuntimeException
     */
    protected function runCommand(string $command): void
    {
        $this->line(sprintf(
            "<fg=blue>➜</> <fg=white>%s</>",
            $command
        ));

        Pool::call($command, false);
    }
}
