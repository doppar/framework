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
            $this->line('<bg=yellow;options=bold> WARNING </> This will drop all database tables!');
            $this->newLine();

            // Basic confirmation implementation
            $this->line('Are you sure you want to proceed? (yes/no) [no]');
            $response = trim(fgets(STDIN));

            if (strtolower($response) !== 'yes') {
                $this->line('<bg=blue;options=bold> INFO </> Command cancelled');
                $this->newLine();
                return 0;
            }

            $this->line('<fg=yellow>♻️  Refreshing database...</>');
            $this->newLine();

            Schema::disableForeignKeyConstraints();
            $tables = DB::dropAllTables();
            Schema::enableForeignKeyConstraints();

            $this->line('<fg=green>✔ Dropped ' . $tables . ' tables</>');
            $this->newLine();

            $status = $this->migrator->run();

            $this->line('<bg=green;options=bold> SUCCESS </> Database refreshed successfully');
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
