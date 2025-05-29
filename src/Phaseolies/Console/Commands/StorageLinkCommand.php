<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class StorageLinkCommand extends Command
{
    protected static $defaultName = 'storage:link';

    protected function configure()
    {
        $this
            ->setName('storage:link')
            ->setDescription('Create a symbolic link from public/storage to storage/app/public.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $links = config('filesystem.links');

        if (empty($links)) {
            $output->writeln('<error>No symbolic links configured in config/filesystems.php</error>');
            return Command::FAILURE;
        }

        foreach ($links as $link => $target) {
            if (is_link($link)) {
                $currentTarget = readlink($link);

                if ($currentTarget === $target) {
                    $output->writeln("<info>✓ Link already exists</info>");
                    continue;
                }

                $output->writeln("<comment>✗ Link exists but points elsewhere. Replacing...</comment>");
                unlink($link);
            } elseif (file_exists($link)) {
                $output->writeln("<comment>✗ File/directory exists at {$link}. Removing...</comment>");
                $rmProcess = Process::fromShellCommandline(sprintf('rm -rf %s', escapeshellarg($link)));
                $rmProcess->run();

                if (!$rmProcess->isSuccessful()) {
                    $output->writeln('<error>Failed to remove: ' . $rmProcess->getErrorOutput() . '</error>');
                    return Command::FAILURE;
                }
            }

            $cmd = sprintf('ln -s %s %s', escapeshellarg($target), escapeshellarg($link));
            $symlinkProcess = Process::fromShellCommandline($cmd);
            $symlinkProcess->run();

            if ($symlinkProcess->isSuccessful()) {
                $output->writeln("<info>✓ Symbolic Link created: {$link} → {$target}</info>");
            } else {
                $output->writeln('<error>Failed to create symlink: ' . $symlinkProcess->getErrorOutput() . '</error>');
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
