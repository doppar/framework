<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Session\SessionManager;

class ClearSessionCommand extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'session:clear';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Clear all sessions from the Application.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        return $this->withTiming(function () {
            SessionManager::store()->flush();

            return Command::SUCCESS;
        }, 'Application sessions have been gracefully cleared.');
    }
}
