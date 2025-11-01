<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;

class SetCreatablePropertyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = "get:column {table}";

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = "Get creatable properties of a given table";

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        $tableName = $this->argument("table");

        $db = db()->getTableColumns($tableName);

        $filteredColumns = array_diff($db, ["id", "created_at", "updated_at"]);
        $filteredColumns = array_values($filteredColumns);

        $this->info('protected $creatable = ' . json_encode($filteredColumns));

        return Command::SUCCESS;
    }
}
