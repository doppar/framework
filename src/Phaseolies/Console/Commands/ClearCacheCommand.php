<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Support\Facades\Cache;
use Phaseolies\Console\Schedule\Command;

class ClearCacheCommand extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'cache:clear';

    /**
     * The command description shown in the Pool command list.
     *
     * This should clearly explain what the command does at a high level.
     */
    protected $description = 'Clear cache files from the storage/framework/cache folder.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        $startTime = microtime(true);

        $this->newLine();

        $cacheDir = base_path('storage/framework/cache');
        $this->deleteDirectoryContents($cacheDir);
        Cache::clear();

        $executionTime = microtime(true) - $startTime;

        $this->newLine();
        $this->line('<bg=green;options=bold> SUCCESS </> Application cache has been gracefully cleared.');
        $this->newLine();
        $this->line(sprintf(
            "<fg=yellow>⏱ Time:</> <fg=white>%.4fs</> <fg=#6C7280>(%d μs)</>",
            $executionTime,
            (int) ($executionTime * 1000000)
        ));
        $this->newLine();
        return 0;

        return 0;
    }

    /**
     * Recursively delete all files and subdirectories in a directory.
     *
     * @param string $directory
     */
    private function deleteDirectoryContents($directory)
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = array_diff(scandir($directory), ['.', '..']);

        foreach ($files as $file) {
            $filePath = $directory . DIRECTORY_SEPARATOR . $file;

            if (is_dir($filePath)) {
                $this->deleteDirectoryContents($filePath);
                @rmdir($filePath);
            } elseif (is_file($filePath)) {
                @unlink($filePath);
            }
        }
    }
}
