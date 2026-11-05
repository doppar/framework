<?php

namespace Phaseolies\Console\Commands\Migrations;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Console\Support\InteractsWithMigrations;
use Phaseolies\Database\Migration\Migrator;

class MigrateCommand extends Command
{
    use InteractsWithMigrations;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'migrate {--path=} {--connection=} {--step} {--pretend} {--force} {--seed}';

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
    public function handle(): int
    {
        return $this->executeWithTiming(function () {
            $connection = $this->resolveConnection();
            $pretend = (bool) $this->option('pretend');

            if (!$this->confirmToProceed('Running migrations in production')) {
                return Command::FAILURE;
            }

            $this->line("<fg=yellow>🔁 " . ($pretend ? 'Previewing' : 'Running') . " migrations on connection: {$connection}</>");
            $this->newLine();

            try {
                $migrations = $this->migrator->run($connection, $this->option('path') ?: null, [
                    'step' => (bool) $this->option('step'),
                    'pretend' => $pretend,
                    'progress' => $this->progressReporter(),
                ]);
            } catch (\Throwable $e) {
                $this->newLine();
                $this->displayError($e->getMessage());

                return Command::FAILURE;
            }

            if (empty($migrations)) {
                $this->displayInfo('Nothing to migrate');
            } elseif (!$pretend) {
                $this->newLine();
                $this->displaySuccess(count($migrations) . ' ' . (count($migrations) === 1 ? 'migration' : 'migrations') . ' executed');
            }

            if ($this->option('seed') && !$pretend) {
                return $this->runSeeders();
            }

            return Command::SUCCESS;
        });
    }
}
