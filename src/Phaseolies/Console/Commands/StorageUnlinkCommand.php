<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StorageUnlinkCommand extends Command
{
    protected static $defaultName = 'storage:unlink';

    protected function configure()
    {
        $this
            ->setName('storage:unlink')
            ->setDescription('Unlink the symbolic link from public/storage to storage/app/public.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheDir = \public_path('storage');

        @unlink($cacheDir);

        $output->writeln('<info>Unlinked the symbolic link successfully</info>');
        return Command::SUCCESS;
    }
}
