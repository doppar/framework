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
        return $this->executeWithTiming(function() {
            $seedName = $this->argument('seed');

            if ($seedName) {
                $seederClass = 'Database\\Seeders\\' . $seedName;

                if (!class_exists($seederClass)) {
                    $this->displayError('Seeder not found: ' . $seedName);
                    return 1;
                }

                $seeder = new $seederClass();
                $seeder->run();
            } else {
                $databaseSeeder = new DatabaseSeeder();
                $databaseSeeder->run();
            }

            $this->displaySuccess('Seeds executed successfully');

            return 0;
        });
    }
}
