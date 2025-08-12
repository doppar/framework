<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use RuntimeException;

class ViewClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'view:clear';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Clear all compiled view files';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return $this->withTiming(function() {
            $viewCacheDir = base_path('storage/framework/views');
            $this->clearViewCache($viewCacheDir);
            return 0;
        }, 'Compiled views cleared successfully');
    }

    /**
     * Clear the view cache directory.
     */
    protected function clearViewCache(string $viewCacheDir): void
    {
        if (!is_dir($viewCacheDir)) {
            throw new RuntimeException("View cache directory does not exist: $viewCacheDir");
        }

        $files = glob($viewCacheDir . '/*');
        $count = 0;

        foreach ($files as $file) {
            if (is_file($file)) {
                if (@unlink($file)) {
                    $count++;
                }
            }
        }

        $this->line('<fg=yellow>🗑️  Cleared:</> <fg=white>' . $count . ' compiled view files</>');
    }
}
