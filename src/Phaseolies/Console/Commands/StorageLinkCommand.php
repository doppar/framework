<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use Symfony\Component\Process\Process;
use RuntimeException;

class StorageLinkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'storage:link';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Create symbolic links from public/storage to storage/app/public';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return $this->executeWithTiming(function() {
            $links = config('filesystem.links');

            if (empty($links)) {
                $this->displayError('No symbolic links configured in config/filesystems.php');
                return 1;
            }

            foreach ($links as $link => $target) {
                $this->processLink($link, $target);
            }

            return 0;
        });
    }

    /**
     * Process a single symbolic link.
     */
    protected function processLink(string $link, string $target): void
    {
        if (is_link($link)) {
            $currentTarget = readlink($link);

            if ($currentTarget === $target) {
                $this->line('<fg=green>✓ Link already exists:</> <fg=white>' . $link . ' → ' . $target . '</>');
                return;
            }

            $this->line('<fg=yellow>⚠️  Link exists but points elsewhere. Replacing...</>');
            unlink($link);
        } elseif (file_exists($link)) {
            $this->line('<fg=yellow>⚠️  File/directory exists at:</> <fg=white>' . $link . '</> <fg=yellow>Removing...</>');
            $this->removeExistingPath($link);
        }

        $this->createSymlink($link, $target);
    }

    /**
     * Remove existing file/directory at path.
     */
    protected function removeExistingPath(string $path): void
    {
        $process = Process::fromShellCommandline(sprintf('rm -rf %s', escapeshellarg($path)));
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('Failed to remove: ' . $process->getErrorOutput());
        }
    }

    /**
     * Create a new symbolic link.
     */
    protected function createSymlink(string $link, string $target): void
    {
        $process = Process::fromShellCommandline(
            sprintf('ln -s %s %s', escapeshellarg($target), escapeshellarg($link))
        );
        $process->run();

        if ($process->isSuccessful()) {
            $this->line('<fg=green>✓ Created symbolic link:</> <fg=white>' . $link . ' → ' . $target . '</>');
        } else {
            throw new RuntimeException('Failed to create symlink: ' . $process->getErrorOutput());
        }
    }
}
