<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Phaseolies\Database\Migration\Migrator;
use Phaseolies\Database\Database;
use Phaseolies\Database\Migration\MigrationRepository;

class MigrateCommand extends Command
{
    protected static $defaultName = 'migrate';

    protected Migrator $migrator;

    public function __construct(?Migrator $migrator = null)
    {
        parent::__construct();

        $this->migrator = $migrator ?? $this->createDefaultMigrator();
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

    protected function createDefaultMigrator(): Migrator
    {
        $db = new Database();
        $repository = new MigrationRepository($db);
        $migrationPath = \database_path('migrations');

        return new Migrator($repository, $migrationPath);
    }
}
