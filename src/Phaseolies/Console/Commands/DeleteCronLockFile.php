<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteCronLockFile extends Command
{
    protected static $defaultName = 'cron:clear';

    protected function configure()
    {
        $this
            ->setName('cron:clear')
            ->setDescription('Delete cron files from storage/schedule directory.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheDir = base_path() . '/storage/schedule';

        $this->deleteDirectoryContents($cacheDir);

        $output->writeln('<info>Cron file deleted successfully</info>');
        return Command::SUCCESS;
    }

    /**
     * Recursively delete all files and subdirectories in a directory.
     *
     * @param string $directory
     */
    private function deleteDirectoryContents($directory)
    {
        $files = array_diff(scandir($directory), ['.', '..']);

        foreach ($files as $file) {
            $filePath = $directory . DIRECTORY_SEPARATOR . $file;
            if (is_dir($filePath)) {
                $this->deleteDirectoryContents($filePath);
                rmdir($filePath);
            } else {
                unlink($filePath);
            }
        }
    }
}
