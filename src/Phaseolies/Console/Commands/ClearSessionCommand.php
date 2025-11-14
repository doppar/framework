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
        return $this->withTiming(function() {
            $sessionDir = base_path('storage/framework/sessions');

            $files = glob($sessionDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }

            session()->flush();
            return Command::SUCCESS;
        }, 'Application sessions have been gracefully cleared.');
    }
}
