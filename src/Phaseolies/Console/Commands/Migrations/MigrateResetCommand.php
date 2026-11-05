<?php

namespace Phaseolies\Console\Commands\Migrations;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Console\Support\InteractsWithMigrations;
use Phaseolies\Database\Migration\Migrator;

class MigrateResetCommand extends Command
{
    use InteractsWithMigrations;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'migrate:reset {--connection=} {--pretend} {--force}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Roll back all database migrations';

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

            if (!$this->confirmToProceed("This will roll back every migration on connection: {$connection}", true)) {
                return Command::FAILURE;
            }

            $this->line("<fg=yellow>⏪ " . ($pretend ? 'Previewing reset' : 'Resetting migrations') . " on connection: {$connection}</>");
            $this->newLine();

            try {
                $rolledBack = $this->migrator->reset($connection, [
                    'pretend' => $pretend,
                    'progress' => $this->progressReporter(),
                ]);
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
