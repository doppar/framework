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
    protected $description = 'Optimizes application performance by clearing and rebuilding caches (config, routes, views)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        $startTime = microtime(true);
        $this->newLine();

        $commands = [
            'cache:clear',        // Clear application cache
            'route:clear',        // Clear route cache
            'route:cache',        // Rebuild route cache
            'config:clear',       // Clear configuration cache
            'config:cache',       // Rebuild configuration cache
            'view:clear',         // Clear compiled views
            'view:cache',         // Recompile views
        ];

        try {
            $this->line('<fg=yellow>⚡ Running application optimization commands:</>');
            $this->newLine();

            foreach ($commands as $command) {
                $this->runCommand($command);
            }

            $executionTime = microtime(true) - $startTime;
            $this->newLine();
            $this->line('<bg=green;options=bold> SUCCESS </> Application optimization completed successfully');
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
            return 1;
        }
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
