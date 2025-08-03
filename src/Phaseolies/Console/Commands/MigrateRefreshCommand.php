<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Support\Facades\Schema;
use Phaseolies\Support\Facades\DB;
use Phaseolies\Database\Migration\Migrator;
use RuntimeException;

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
        $startTime = microtime(true);
        $this->newLine();

        try {

            $allConnections = config('database.connections');

            // Show warning message
            $this->line('<bg=yellow;options=bold> WARNING </> This will drop all database tables from database connection:');
            foreach (array_keys($allConnections) as $name) {
                $this->line('- <fg=white>' . $name . '</>');
            }
            $this->newLine();

            // Confirmation
            $this->line('Are you sure you want to proceed? (yes/no) [no]');
            $response = trim(fgets(STDIN));

            if (strtolower($response) !== 'yes') {
                $this->line('<bg=blue;options=bold> INFO </> Command cancelled');
                $this->newLine();
                return 0;
            }

            // Process each connection
            $totalTablesDropped = 0;
            foreach ($allConnections as $name => $config) {
                $this->line("<fg=yellow>♻️  Refreshing database on connection: {$name}</>");

                Schema::connection($name)->disableForeignKeyConstraints();
                $tablesDropped = DB::connection($name)->dropAllTables();
                Schema::connection($name)->enableForeignKeyConstraints();

                $totalTablesDropped += $tablesDropped;
                $this->line("<fg=green>✔ Dropped {$tablesDropped} tables from {$name}</>");
                $this->newLine();
            }

            $this->line('<fg=yellow>🔁 Running migrations</>');
            $status = $this->migrator->run();

            $this->line('<bg=green;options=bold> SUCCESS </> Database refresh completed');
            $this->newLine();
            $this->line('<fg=yellow>📊 Migrations Executed:</>');
            foreach ($status as $migration) {
                $this->line('- <fg=white>' . $migration . '</>');
            }

            $executionTime = microtime(true) - $startTime;
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
            $this->line('<fg=red>✖ ERROR: ' . $e->getMessage() . '</>');
            $this->line('<fg=yellow>📁 FILE: ' . $e->getFile() . '</>');
            $this->line('<fg=yellow>📍 LINE: ' . $e->getLine() . '</>');
            $this->newLine();
            return 1;
        }
    }
}
