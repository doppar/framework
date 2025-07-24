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
        $startTime = microtime(true);
        $this->newLine();

        try {
            $viewCacheDir = base_path('storage/framework/views');

            $this->line('<bg=green;options=bold> SUCCESS </> Compiled views cleared successfully');
            $this->newLine();
            $this->clearViewCache($viewCacheDir);

            $executionTime = microtime(true) - $startTime;
            $this->newLine();
            $this->line(sprintf(
                "<fg=yellow>⏱ Time:</> <fg=white>%.4fs</> <fg=#6C7280>(%d μs)</>",
                $executionTime,
                (int) ($executionTime * 1000000)
            ));
            $this->newLine();

            return 0;
        } catch (RuntimeException $e) {
            $this->line('<bg=red;options=bold> ERROR </> ' . $e->getMessage());
            $this->newLine();
            return 1;
        }
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
