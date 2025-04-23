<?php

namespace Phaseolies\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command;

class DBSeedCommand extends Command
{
    protected static $defaultName = 'db:seed';

    protected function configure()
    {
        $this
            ->setName('db:seed')
            ->setDescription('Run database seeds.')
            ->addArgument('seed', InputArgument::OPTIONAL, 'The name of the seed to run (optional).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $seedName = $input->getArgument('seed');

        if ($seedName) {
            $seederClass = 'Database\\Seeders\\' . $seedName;

            if (!class_exists($seederClass)) {
                $output->writeln("<error>Seeder {$seedName} not found</error>");
                return Command::FAILURE;
            }

            $seeder = new $seederClass();
            $seeder->run();
        } else {
            $databaseSeeder = new DatabaseSeeder();
            $databaseSeeder->run();
        }

        $output->writeln('<info>Seeds executed successfully</info>');
        return Command::SUCCESS;
    }
}
