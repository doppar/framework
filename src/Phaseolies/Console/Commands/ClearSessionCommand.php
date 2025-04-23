<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class ClearSessionCommand extends Command
{
    protected static $defaultName = 'session:clear';

    protected function configure()
    {
        $this
            ->setName('session:clear')
            ->setDescription('Clear all sessions from the Applicaion.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sessionDir = base_path('storage/framework/sessions');

        $files = glob($sessionDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        session()->flush();

        $output->writeln('<info>All session files cleared successfully</info>');
        return Command::SUCCESS;
    }
}
