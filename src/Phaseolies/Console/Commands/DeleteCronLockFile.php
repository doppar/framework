<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;

class DeleteCronLockFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'cron:clear';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Delete cron files from storage/schedule directory';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return $this->withTiming(function () {
            $cacheDir = base_path() . '/storage/schedule';

            if (!is_dir($cacheDir)) {
                $this->displayInfo('No cron files directory found.');
                return Command::SUCCESS;
            }

            $this->deleteDirectoryContents($cacheDir);

            return Command::SUCCESS;
        }, 'Cron files deleted successfully');
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
