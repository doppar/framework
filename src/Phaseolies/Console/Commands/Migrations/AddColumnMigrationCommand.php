<?php

namespace Phaseolies\Console\Commands\Migrations;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Database\Migration\MigrationCreator;

class AddColumnMigrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:migration {name} {--create=} {--table=} {--column=} {--type=} {--after=}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Creates a new migration file with optional column addition';

    /**
     * Create a new command instance.
     *
     * @param MigrationCreator $creator
     * @return void
     */
    public function __construct(protected MigrationCreator $creator)
    {
        parent::__construct();
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
            $column = $this->option('column');
            $type = $this->option('type');
            $after = $this->option('after');

            if (!$table && is_string($create)) {
                $table = $create;
                $create = true;
            }

            $file = $this->creator->create(
                $name,
                $this->getMigrationPath(),
                $table,
                $create,
                $column,
                $type,
                $after
            );

            $this->displaySuccess('Migration created successfully.');
            $this->line("<fg=yellow>📁 File:</> <fg=white>{$file}</>");
            
            return Command::SUCCESS;
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
