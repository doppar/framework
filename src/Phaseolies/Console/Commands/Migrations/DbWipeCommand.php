<?php

namespace Phaseolies\Console\Commands\Migrations;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Console\Support\InteractsWithMigrations;
use Phaseolies\Support\Facades\DB;

class DbWipeCommand extends Command
{
    use InteractsWithMigrations;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'db:wipe {--connection=} {--force}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Drop all tables from the database';

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

            try {
                $database = DB::connection($connection);

                $database->disableForeignKeyConstraints();

                try {
                    $dropped = $database->dropAllTables();
                } finally {
                    $database->enableForeignKeyConstraints();
                }
            } catch (\Throwable $e) {
                $this->displayError("Failed to wipe database [{$connection}]: {$e->getMessage()}");

                return Command::FAILURE;
            }

            $this->displaySuccess("Dropped {$dropped} " . ($dropped === 1 ? 'table' : 'tables') . " from {$connection}");

            return Command::SUCCESS;
        });
    }
}
