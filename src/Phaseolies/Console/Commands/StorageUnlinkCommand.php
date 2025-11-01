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
        return $this->executeWithTiming(function() {
            $linkPath = public_path('storage');

            if (!is_link($linkPath)) {
                $this->displayWarning('No symbolic link found at:');
                $this->line('<fg=white>' . $linkPath . '</>');
                return Command::FAILURE;
            }

            if (@unlink($linkPath)) {
                $this->displaySuccess('Symbolic link removed successfully');
                $this->line('<fg=yellow>🗑️  Removed:</> <fg=white>' . $linkPath . '</>');
                return Command::SUCCESS;
            }

            throw new RuntimeException('Failed to remove symbolic link');
        });
    }
}
