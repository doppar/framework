<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Support\Facades\Route;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Command\Command;

class RouteCacheCommand extends Command
{
    protected static $defaultName = 'route:cache';

    protected function configure()
    {
        $this
            ->setName('route:cache')
            ->setDescription('Cache the routes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheDir = base_path('storage/framework/cache');

        if (!is_dir($cacheDir)) {
            $output->writeln("<error>Cache directory does not exist: $cacheDir</error>");
            return Command::FAILURE;
        }

        Route::cacheRoutes();

        $output->writeln('<info>Routes has been cached successfully</info>');
        return Command::SUCCESS;
    }
}
