<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Database\Migration\Migrator;

class MigrateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'migrate {--path=}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Run the database migrations';

    /**
     * The migrator instance.
     *
     * @var Migrator
     */
    protected Migrator $migrator;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        $this->migrator = app('migrator');
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return $this->executeWithTiming(function() {
            $path = $this->option('path');

            if ($path) {
                $status = $this->migrator->run([$path]);
            } else {
                $status = $this->migrator->run();
            }

            if (empty($status)) {
                $this->displayInfo('Nothing to migrate');
            } else {
                $this->displaySuccess('Database migrated successfully');
                $this->line('<fg=yellow>📊 Migrations Executed:</>');
                foreach ($status as $migration) {
                    $this->line('- <fg=white>' . $migration . '</>');
                }
            }

            return 0;
        });
    }
}
