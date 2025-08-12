<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Database\Migration\MigrationCreator;

class CreateMigrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:migration {name} {--create=} {--table=}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Creates a new migration file';

    /**
     * The migration creator instance.
     *
     * @var MigrationCreator
     */
    protected MigrationCreator $creator;

    /**
     * Create a new command instance.
     *
     * @param MigrationCreator $creator
     * @return void
     */
    public function __construct(MigrationCreator $creator)
    {
        parent::__construct();

        $this->creator = $creator;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return $this->executeWithTiming(function() {
            $name = $this->argument('name');
            $table = $this->option('table');
            $create = $this->option('create') ?: false;

            if (!$table && is_string($create)) {
                $table = $create;
                $create = true;
            }

            $file = $this->creator->create(
                $name,
                $this->getMigrationPath(),
                $table,
                $create
            );

            $this->displaySuccess('Migration created successfully.');
            $this->line("<fg=yellow>📁 File:</> <fg=white>{$file}</>");
            return 0;
        });
    }

    /**
     * Get the path to the migration directory.
     *
     * @return string
     */
    protected function getMigrationPath(): string
    {
        return base_path('database/migrations');
    }
}
