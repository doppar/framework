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
    public function handle(): int
    {
        return $this->executeWithTiming(function() {
            $linkPath = public_path('storage');

            if (!$this->linkExists($linkPath)) {
                $this->displayWarning('No symbolic link found at:');
                $this->line('<fg=white>' . $linkPath . '</>');
                return Command::FAILURE;
            }

            if ($this->removeLink($linkPath)) {
                $this->displaySuccess('Symbolic link removed successfully');
                $this->line('<fg=yellow>🗑️  Removed:</> <fg=white>' . $linkPath . '</>');
                return Command::SUCCESS;
            }

            throw new RuntimeException('Failed to remove symbolic link');
        });
    }

    /**
     * Determine whether the given path is a symbolic link.
     */
    protected function linkExists(string $linkPath): bool
    {
        return is_link($linkPath);
    }

    /**
     * Remove a linked path.
     *
     * Windows directory links may require rmdir() instead of unlink().
     */
    protected function removeLink(string $linkPath): bool
    {
        if (@unlink($linkPath)) {
            return true;
        }

        return is_dir($linkPath) && @rmdir($linkPath);
    }
}
