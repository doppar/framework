<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Support\Facades\Route;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Command\Command;

class ClearRouteCacheCommand extends Command
{
    protected static $defaultName = 'route:clear';

    protected function configure()
    {
        $this
            ->setName('route:clear')
            ->setDescription('Clear all route files from the storage/framework/cache folder.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Route::clearRouteCache();

        $output->writeln('<info>Route cache cleared successfully</info>');
        return Command::SUCCESS;
    }
}
