<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClearConfigCommand extends Command
{
    protected static $defaultName = 'config:clear';

    protected function configure()
    {
        $this
            ->setName('config:clear')
            ->setDescription('Clear all config cache data.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        app('config')->clearCache();

        $output->writeln('<info>Config cache cleared successfully</info>');

        return Command::SUCCESS;
    }
}
