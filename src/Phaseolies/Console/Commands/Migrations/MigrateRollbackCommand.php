<?php

namespace Phaseolies\Console\Commands\Migrations;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Console\Support\InteractsWithMigrations;
use Phaseolies\Database\Migration\Migrator;

class MigrateRollbackCommand extends Command
{
    use InteractsWithMigrations;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'migrate:rollback {--connection=} {--step=} {--batch=} {--pretend} {--force}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Roll back the last batch of migrations (or the last --step=N, or one --batch=N)';

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
            $pretend = (bool) $this->option('pretend');

            try {
                $step = $this->integerOption('step');
                $batch = $this->integerOption('batch');
            } catch (\RuntimeException $e) {
                $this->displayError($e->getMessage());

                return Command::FAILURE;
            }

            if ($step !== null && $batch !== null) {
                $this->displayError('Use either --step or --batch, not both.');

                return Command::FAILURE;
            }

            if (!$this->confirmToProceed('Rolling back migrations in production')) {
                return Command::FAILURE;
            }

            $this->line("<fg=yellow>⏪ " . ($pretend ? 'Previewing rollback' : 'Rolling back migrations') . " on connection: {$connection}</>");
            $this->newLine();

            try {
                $options = ['pretend' => $pretend, 'progress' => $this->progressReporter()];

                if ($step !== null) {
                    $options['step'] = $step;
                }

                if ($batch !== null) {
                    $options['batch'] = $batch;
                }

                $rolledBack = $this->migrator->rollback($connection, $options);
            } catch (\Throwable $e) {
                $this->newLine();
                $this->displayError($e->getMessage());

                return Command::FAILURE;
            }

            if (empty($rolledBack)) {
                $this->displayInfo('Nothing to roll back');
            } elseif (!$pretend) {
                $this->newLine();
                $this->displaySuccess(count($rolledBack) . ' ' . (count($rolledBack) === 1 ? 'migration' : 'migrations') . ' rolled back');
            }

            return Command::SUCCESS;
        });
    }
}
