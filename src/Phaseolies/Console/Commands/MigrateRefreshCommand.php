<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Support\Facades\Schema;
use Phaseolies\Support\Facades\DB;
use Phaseolies\Database\Migration\Migrator;

class MigrateRefreshCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'migrate:fresh {--connection=}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Drop all tables and re-run all migrations for the specified or default connection';

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
        return $this->executeWithTiming(function () {
            $connection = $this->option('connection') ?: config('database.default');

            $this->displayWarning("This will drop all tables from database connection: <fg=white>{$connection}</>");
            $this->line('Are you sure you want to proceed? (yes/no) [no]');
            $response = trim(fgets(STDIN));

            if (strtolower($response) !== 'yes') {
                $this->displayInfo('Command cancelled');
                return Command::SUCCESS;
            }

            $this->newLine();
            $this->line("<fg=yellow>♻️  Refreshing database on connection: {$connection}</>");

            try {
                Schema::connection($connection)->disableForeignKeyConstraints();

                $tablesDropped = DB::connection($connection)->dropAllTables();

                Schema::connection($connection)->enableForeignKeyConstraints();

                $this->newLine();
                $this->line("<fg=green>✔ Dropped {$tablesDropped} tables from {$connection}</>");
            } catch (\Throwable $e) {
                $this->displayError("Failed to refresh database [{$connection}]: {$e->getMessage()}");
                return Command::FAILURE;
            }

            $this->newLine();
            $this->line('<fg=yellow>🔁 Running migrations</>');
            $this->newLine();
            $migrations = $this->migrator->run($connection);

            $this->displaySuccess('Database refresh completed');
            $this->line('<fg=yellow>📊 Migrations Executed:</>');
            foreach ($migrations as $migration) {
                $this->line('- <fg=white>' . $migration . '</>');
            }

            return Command::SUCCESS;
        });
    }
}
