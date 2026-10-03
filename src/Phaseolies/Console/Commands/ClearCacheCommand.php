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
    protected $name = 'cache:clear {--store= : Clear only this store from the caching config, leaving the framework cache files alone}';

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
    public function handle(): int
    {
        $store = $this->option('store');

        if ($store) {
            return $this->withTiming(function () use ($store) {
                Cache::store($store)->clear();

                return Command::SUCCESS;
            }, "Cache store [{$store}] has been cleared.");
        }

        return $this->withTiming(function() {
            $cacheDir = base_path('storage/framework/cache');
            $this->deleteDirectoryContents($cacheDir);
            Cache::clear();
            return Command::SUCCESS;
        }, 'Application cache has been gracefully cleared.');
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
