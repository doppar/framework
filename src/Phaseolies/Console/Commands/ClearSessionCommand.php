<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;

class ClearSessionCommand extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'session:clear';

    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $description = 'Clear all sessions from the Applicaion.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        $startTime = microtime(true);

        $this->newLine();

        $sessionDir = base_path('storage/framework/sessions');

        $files = glob($sessionDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        session()->flush();

        $executionTime = microtime(true) - $startTime;

        $this->newLine();
        $this->line('<bg=green;options=bold> SUCCESS </> Application sessions have been gracefully cleared.');
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
