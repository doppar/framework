<?php

namespace Phaseolies\Console\Commands\Migrations;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Console\Support\InteractsWithMigrations;
use Phaseolies\Database\Migration\Migrator;

class MigrateRefreshCommand extends Command
{
    use InteractsWithMigrations;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'migrate:refresh {--connection=} {--step=} {--seed} {--force}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Roll back all migrations (or the last --step=N) and run them again';

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

            try {
                $step = $this->integerOption('step');
            } catch (\RuntimeException $e) {
                $this->displayError($e->getMessage());

                return Command::FAILURE;
            }

            $scope = $step === null ? 'every migration' : "the last {$step} migration(s)";

            if (!$this->confirmToProceed("This will roll back {$scope} on connection: {$connection} and run them again", true)) {
                return Command::FAILURE;
            }

            $progress = $this->progressReporter();

            try {
                $this->line("<fg=yellow>⏪ Rolling back on connection: {$connection}</>");
                $this->newLine();

                $rolledBack = $step === null
                    ? $this->migrator->reset($connection, ['progress' => $progress])
                    : $this->migrator->rollback($connection, ['step' => $step, 'progress' => $progress]);

                $this->newLine();
                $this->line('<fg=yellow>🔁 Running migrations</>');
                $this->newLine();

                $migrated = $this->migrator->run($connection, null, ['progress' => $progress]);
            } catch (\Throwable $e) {
                $this->newLine();
                $this->displayError($e->getMessage());

                return Command::FAILURE;
            }

            $this->newLine();
            $this->displaySuccess(sprintf('Refreshed (%d rolled back, %d executed)', count($rolledBack), count($migrated)));

            if ($this->option('seed')) {
                return $this->runSeeders();
            }

            return Command::SUCCESS;
        });
    }
}
