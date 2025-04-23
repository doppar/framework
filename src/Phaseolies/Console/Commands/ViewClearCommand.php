<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ViewClearCommand extends Command
{
    protected static $defaultName = 'view:clear';

    protected function configure()
    {
        $this
            ->setName('view:clear')
            ->setDescription('Clear all compiled view files from the storage/views folder.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $viewCacheDir = base_path() . '/storage/framework/views';

        if (!is_dir($viewCacheDir)) {
            $output->writeln("<error>View cache directory does not exist: $viewCacheDir</error>");
            return Command::FAILURE;
        }

        $files = glob($viewCacheDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $output->writeln('<info>Compiled views cleared successfully!</info>');
        return Command::SUCCESS;
    }
}
