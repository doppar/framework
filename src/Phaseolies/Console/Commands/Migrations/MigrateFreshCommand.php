<?php

namespace Phaseolies\Console\Commands\Migrations;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Console\Support\InteractsWithMigrations;
use Phaseolies\Support\Facades\DB;
use Phaseolies\Database\Migration\Migrator;

class MigrateFreshCommand extends Command
{
    use InteractsWithMigrations;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'migrate:fresh {--connection=} {--seed} {--force}';

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
    public function handle(): int
    {
        return $this->executeWithTiming(function () {
            $connection = $this->resolveConnection();

            if (!$this->confirmToProceed("This will drop all tables from database connection: {$connection}", true)) {
                return Command::FAILURE;
            }

            $this->line("<fg=yellow>♻️  Refreshing database on connection: {$connection}</>");

            try {
                $database = DB::connection($connection);

                $database->disableForeignKeyConstraints();

                try {
                    $tablesDropped = $database->dropAllTables();
                } finally {
                    $database->enableForeignKeyConstraints();
                }

                $this->newLine();
                $this->line("<fg=green>✔ Dropped {$tablesDropped} tables from {$connection}</>");
            } catch (\Throwable $e) {
                $this->displayError("Failed to refresh database [{$connection}]: {$e->getMessage()}");
                return Command::FAILURE;
            }

            $this->newLine();
            $this->line('<fg=yellow>🔁 Running migrations</>');
            $this->newLine();

            try {
                $migrations = $this->migrator->run($connection, null, ['progress' => $this->progressReporter()]);
            } catch (\Throwable $e) {
                $this->newLine();
                $this->displayError($e->getMessage());
                return Command::FAILURE;
            }

            $this->newLine();
            $this->displaySuccess('Database refresh completed (' . count($migrations) . ' executed)');

            if ($this->option('seed')) {
                return $this->runSeeders();
            }

            return Command::SUCCESS;
        });
    }
}
