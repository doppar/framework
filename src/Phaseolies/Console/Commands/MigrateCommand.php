<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Phaseolies\Database\Migration\Migrator;

class MigrateCommand extends Command
{
    protected static $defaultName = 'migrate';

    protected Migrator $migrator;

    public function __construct()
    {
        parent::__construct();

        $this->migrator = app('migrator');
    }

    protected function configure()
    {
        $this->setName('migrate')->setDescription('Run the database migrations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $status = $this->migrator->run();
        if (empty($status)) {
            $output->writeln('<info>Nothing to migrate.</info>');
        } else {
            $output->writeln('<info>Database migrated successfully.</info>');
        }

        return Command::SUCCESS;
    }
}
