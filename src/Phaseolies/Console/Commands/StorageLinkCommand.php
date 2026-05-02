<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
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
    public function handle(): int
    {
        return $this->executeWithTiming(function () {
            $links = config('filesystem.links');

            if (empty($links)) {
                $this->displayError('No symbolic links configured in config/filesystems.php');
                return Command::FAILURE;
            }

            foreach ($links as $link => $target) {
                $this->processLink($link, $target);
            }

            return Command::SUCCESS;
        });
    }

    /**
     * Process a single symbolic link
     *
     * @param string $link
     * @param string $target
     * @return void
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
     * Remove existing file/directory at path
     *
     * @param string $path
     * @return void
     */
    protected function removeExistingPath(string $path): void
    {
        if (is_link($path)) {
            if (@unlink($path) || (is_dir($path) && @rmdir($path))) {
                return;
            }

            throw new RuntimeException('Failed to remove existing link: ' . $path);
        }

        if (is_file($path)) {
            if (@unlink($path)) {
                return;
            }

            throw new RuntimeException('Failed to remove file: ' . $path);
        }

        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $itemPath = $item->getPathname();

            if ($item->isDir() && !$item->isLink()) {
                if (!@rmdir($itemPath)) {
                    throw new RuntimeException('Failed to remove directory: ' . $itemPath);
                }

                continue;
            }

            if (!@unlink($itemPath)) {
                throw new RuntimeException('Failed to remove file: ' . $itemPath);
            }
        }

        if (!@rmdir($path)) {
            throw new RuntimeException('Failed to remove directory: ' . $path);
        }
    }

    /**
     * Create a new symbolic link
     *
     * @param string $link
     * @param string $target
     * @return void
     */
    protected function createSymlink(string $link, string $target): void
    {
        $directory = dirname($link);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Failed to create link directory: ' . $directory);
        }

        if ($this->createNativeSymlink($target, $link) || $this->createWindowsFallbackLink($target, $link)) {
            $this->line('<fg=green>✓ Created symbolic link:</> <fg=white>' . $link . ' → ' . $target . '</>');
            return;
        }

        throw new RuntimeException('Failed to create symbolic link for: ' . $link);
    }

    /**
     * Attempt to create a symlink using PHP's built-in support
     *
     * @param string $target
     * @param string $link
     * @return bool
     */
    protected function createNativeSymlink(string $target, string $link): bool
    {
        return function_exists('symlink') && @symlink($target, $link);
    }

    /**
     * Attempt a Windows-specific fallback when native symlink creation is unavailable
     *
     * @param string $target
     * @param string $link
     * @return bool
     */
    protected function createWindowsFallbackLink(string $target, string $link): bool
    {
        if (!$this->isWindows()) {
            return false;
        }

        $process = new Process($this->buildWindowsLinkCommand($target, $link));
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Build the Windows command used to create a link or junction.
     *
     * @return array<int, string>
     */
    protected function buildWindowsLinkCommand(string $target, string $link): array
    {
        if (is_dir($target)) {
            return ['cmd', '/c', 'mklink', '/J', $link, $target];
        }

        return ['cmd', '/c', 'mklink', $link, $target];
    }

    /**
     * Determine whether the current operating system is Windows.
     *
     * @return bool
     */
    protected function isWindows(): bool
    {
        return stripos(PHP_OS_FAMILY, 'Windows') !== false;
    }
}
