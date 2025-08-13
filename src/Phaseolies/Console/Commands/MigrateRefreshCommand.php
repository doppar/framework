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
    protected $name = 'migrate:fresh';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Drop all tables and re-run all migrations';

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
            $allConnections = config('database.connections');

            // Show warning message
            $this->displayWarning('This will drop all database tables from database connection:');
            foreach (array_keys($allConnections) as $name) {
                $this->line('- <fg=white>' . $name . '</>');
            }
            $this->newLine();

            // Confirmation
            $this->line('Are you sure you want to proceed? (yes/no) [no]');
            $response = trim(fgets(STDIN));

            if (strtolower($response) !== 'yes') {
                $this->displayInfo('Command cancelled');
                return 0;
            }

            // Process each connection
            $totalTablesDropped = 0;
            foreach ($allConnections as $name => $config) {
                $this->line("<fg=yellow>♻️  Refreshing database on connection: {$name}</>");

                Schema::connection($name)->disableForeignKeyConstraints();

                /**
                 * @var int $tablesDropped
                 */
                $tablesDropped = DB::connection($name)->dropAllTables();

                Schema::connection($name)->enableForeignKeyConstraints();

                $totalTablesDropped += $tablesDropped;
                $this->line("<fg=green>✔ Dropped {$tablesDropped} tables from {$name}</>");
                $this->newLine();
            }

            $this->line('<fg=yellow>🔁 Running migrations</>');
            $status = $this->migrator->run();

            $this->displaySuccess('Database refresh completed');
            $this->line('<fg=yellow>📊 Migrations Executed:</>');
            foreach ($status as $migration) {
                $this->line('- <fg=white>' . $migration . '</>');
            }

            return 0;
        });
    }
}
