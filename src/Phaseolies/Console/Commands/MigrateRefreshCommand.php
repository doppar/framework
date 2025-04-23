<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Support\Facades\Schema;
use Phaseolies\Support\Facades\DB;
use Phaseolies\Database\Migration\Migrator;
use Phaseolies\Database\Migration\MigrationRepository;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Command\Command;

class MigrateRefreshCommand extends Command
{
    protected static $defaultName = 'migrate:fresh';

    protected Migrator $migrator;

    public function __construct(?Migrator $migrator = null)
    {
        parent::__construct();

        $this->migrator = $migrator ?? $this->createDefaultMigrator();
    }

    protected function configure()
    {
        $this
            ->setName('migrate:fresh')
            ->setDescription('Rolls back all migrations and re-runs them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Schema::disableForeignKeyConstraints();
        DB::dropAllTables();
        Schema::enableForeignKeyConstraints();

        $this->migrator->run();

        $output->writeln('<info>Migrations have been refreshed successfully.</info>');

        return Command::SUCCESS;
    }

    protected function createDefaultMigrator(): Migrator
    {
        $repository = new MigrationRepository();
        $migrationPath = \database_path('migrations');

        return new Migrator($repository, $migrationPath);
    }
}
