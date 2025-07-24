<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use Database\Seeders\DatabaseSeeder;

class DBSeedCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'db:seed {seed?}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Run database seeds.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        $startTime = microtime(true);
        $this->newLine();

        $seedName = $this->argument('seed');

        if ($seedName) {
            $seederClass = 'Database\\Seeders\\' . $seedName;

            if (!class_exists($seederClass)) {
                $this->line('<bg=red;options=bold> ERROR </> Seeder not found: ' . $seedName);
                $this->newLine();
                return 1;
            }

            $seeder = new $seederClass();
            $seeder->run();
        } else {
            $databaseSeeder = new DatabaseSeeder();
            $databaseSeeder->run();
        }

        $this->line('<bg=green;options=bold> SUCCESS </> Seeds executed successfully');

        $executionTime = microtime(true) - $startTime;
        $this->newLine();
        $this->line(sprintf(
            "<fg=yellow>⏱ Time:</> <fg=white>%.4fs</> <fg=#6C7280>(%d μs)</>",
            $executionTime,
            (int) ($executionTime * 1000000)
        ));

        $this->newLine();

        return 0;
    }
}
