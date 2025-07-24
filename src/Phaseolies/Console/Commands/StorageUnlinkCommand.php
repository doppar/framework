<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use RuntimeException;

class StorageUnlinkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'storage:unlink';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Remove the symbolic link from public/storage to storage/app/public';

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
            $linkPath = public_path('storage');

            if (!is_link($linkPath)) {
                $this->line('<bg=yellow;options=bold> WARNING </> No symbolic link found at:');
                $this->newLine();
                $this->line('<fg=white>' . $linkPath . '</>');
                $this->newLine();
                return 1;
            }

            if (@unlink($linkPath)) {
                $this->line('<bg=green;options=bold> SUCCESS </> Symbolic link removed successfully');
                $this->newLine();
                $this->line('<fg=yellow>🗑️  Removed:</> <fg=white>' . $linkPath . '</>');

                $executionTime = microtime(true) - $startTime;
                $this->newLine();
                $this->line(sprintf(
                    "<fg=yellow>⏱ Time:</> <fg=white>%.4fs</> <fg=#6C7280>(%d μs)</>",
                    $executionTime,
                    (int) ($executionTime * 1000000)
                ));
                $this->newLine();

                return 0;
            }

            throw new RuntimeException('Failed to remove symbolic link');
        } catch (RuntimeException $e) {
            $this->line('<bg=red;options=bold> ERROR </> ' . $e->getMessage());
            $this->newLine();
            return 1;
        }
    }
}
