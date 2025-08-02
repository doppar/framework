<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Database\Migration\Migrator;
use RuntimeException;

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
        $startTime = microtime(true);
        $this->newLine();

        try {
            $path = $this->option('path');

            if ($path) {
                $status = $this->migrator->run([$path]);
            } else {
                $status = $this->migrator->run();
            }

            if (empty($status)) {
                $this->line('<bg=blue;options=bold> INFO </> Nothing to migrate');
            } else {
                $this->line('<bg=green;options=bold> SUCCESS </> Database migrated successfully');
                $this->newLine();
                $this->line('<fg=yellow>📊 Migrations Executed:</>');
                foreach ($status as $migration) {
                    $this->line('- <fg=white>' . $migration . '</>');
                }
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
            return 1;
        }
    }
}
