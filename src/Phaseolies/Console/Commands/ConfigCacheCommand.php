<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigCacheCommand extends Command
{
    protected static $defaultName = 'config:cache';

    /**
     * Stores loaded configuration data.
     *
     * @var array<string, mixed>
     */
    protected array $config = [];

    /**
     * Cache file path.
     *
     * @var string
     */
    protected string $cacheFile;

    protected function configure()
    {
        $this
            ->setName('config:cache')
            ->setDescription('Cache the configuration files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        app('config')->loadFromCache();

        $output->writeln('<info>Config cached successfully!</info>');

        return Command::SUCCESS;
    }
}
