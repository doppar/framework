<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Session\SessionManager;

class SessionGcCommand extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'session:gc';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Remove every expired session from the session store.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        return $this->withTiming(function () {
            $removed = SessionManager::store()->gc(SessionManager::config()['lifetime'] * 60);

            $this->info("Removed {$removed} expired session(s).");

            return Command::SUCCESS;
        }, 'Expired sessions have been removed.');
    }
}
