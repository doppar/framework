<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Phaseolies\Database\Migration\MigrationCreator;

class AddColumnMigrationCommand extends Command
{
    protected static $defaultName = 'make:migration';

    protected MigrationCreator $creator;

    public function __construct(MigrationCreator $creator)
    {
        parent::__construct();
        $this->creator = $creator;
    }

    protected function configure()
    {
        $this
            ->setName('make:migration')
            ->setDescription('Creates a new migration file')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the migration')
            ->addOption('create', null, InputOption::VALUE_OPTIONAL, 'The table to be created')
            ->addOption('table', null, InputOption::VALUE_OPTIONAL, 'The table to migrate')
            ->addOption('column', null, InputOption::VALUE_OPTIONAL, 'The column to add')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'The column type')
            ->addOption('after', null, InputOption::VALUE_OPTIONAL, 'The column to place this new column after');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $table = $input->getOption('table');
        $create = $input->getOption('create') ?: false;
        $column = $input->getOption('column');
        $type = $input->getOption('type');
        $after = $input->getOption('after');

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

        $output->writeln("<info>Created Migration:</info> {$file}");

        return Command::SUCCESS;
    }

    protected function getMigrationPath(): string
    {
        return base_path('database/migrations');
    }
}
