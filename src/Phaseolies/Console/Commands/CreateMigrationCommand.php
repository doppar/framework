<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Phaseolies\Database\Migration\MigrationCreator;

class CreateMigrationCommand extends Command
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
            ->setDescription('Create a new migration file')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the migration')
            ->addOption('create', null, InputOption::VALUE_OPTIONAL, 'The table to be created')
            ->addOption('table', null, InputOption::VALUE_OPTIONAL, 'The table to migrate');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $table = $input->getOption('table');
        $create = $input->getOption('create') ?: false;

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

        $output->writeln("<info>Created Migration:</info> {$file}");

        return Command::SUCCESS;
    }

    protected function getMigrationPath(): string
    {
        return base_path('database/migrations');
    }
}
