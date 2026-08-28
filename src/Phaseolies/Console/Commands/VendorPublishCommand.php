<?php

namespace Phaseolies\Console\Commands;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Phaseolies\Console\Schedule\Command;
use Phaseolies\Application;
use FilesystemIterator;

class VendorPublishCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'vendor:publish {--launcher=} {--tag=} {--force}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Publish any publishable assets from vendor packages';

    protected Application $app;

    public function __construct()
    {
        parent::__construct();
        $this->app = app();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        return $this->executeWithTiming(function () {
            $launcher = $this->option('launcher');
            $tag = $this->option('tag');
            $force = $this->option('force');

            if ($launcher) {
                $this->publishLauncher($launcher, $force);
                return Command::SUCCESS;
            }

            if ($tag) {
                $this->publishTag($tag, $force);
                return Command::SUCCESS;
            }

            $this->publishAll($force);

            return Command::SUCCESS;
        });
    }

    protected function publishLauncher(string $launcher, bool $force = false)
    {
        $launcherClass = $this->app->getLauncher($launcher);

        if (!$launcherClass) {
            $this->displayError("Unable to locate launcher: {$launcher}");
            return;
        }

        $paths = $launcherClass->pathsToPublish($launcher);
        $this->publishPaths($paths, $force);

        $this->newLine();
            $this->displaySuccess("Published assets for launcher: {$launcher}");
    }

    protected function publishTag(string $tag, bool $force = false)
    {
        $paths = [];

        foreach ($this->app->getLaunchers() as $launcher) {
            $providerPaths = $launcher->pathsToPublish(null, $tag);
            if (!empty($providerPaths)) {
                $paths = array_merge($paths, $providerPaths);
            }
        }

        if (empty($paths)) {
            $this->displayError("Unable to locate tag: {$tag}");
            return;
        }

        $this->publishPaths($paths, $force);

        $this->newLine();
        $this->displaySuccess("Published assets for tag: {$tag}");
    }

    protected function publishAll(bool $force = false)
    {
        $publishedCount = 0;
        foreach ($this->app->getLaunchers() as $launcher) {
            $paths = $launcher->pathsToPublish();
            if (!empty($paths)) {
                $this->publishPaths($paths, $force);
                $publishedCount++;
            }
        }

        $this->newLine();
        $this->displaySuccess("Published assets from {$publishedCount} launchers");
    }

    protected function publishPaths(array $paths, bool $force = false)
    {
        foreach ($paths as $key => $item) {
            $mappings = is_array($item) ? $item : [$key => $item];

            foreach ($mappings as $from => $to) {
                $method = is_dir($from) ? 'publishDirectory' : 'publishFile';
                $this->{$method}($from, $to, $force);
            }
        }
    }

    protected function publishDirectory(string $from, string $to, bool $force = false)
    {
        if (!is_dir($to)) {
            mkdir($to, 0755, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $to . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755);
                }
            } else {
                $this->publishFile($item->getPathname(), $target, $force);
            }
        }
        $this->newLine();
    }

    protected function publishFile(string $from, string $to, bool $force = false)
    {
        if (file_exists($to) && !$force) {
            $this->displayWarning("Skipping: File already exists at {$to}");
            return;
        }

        $directory = dirname($to);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        copy($from, $to);
        $this->line("→ {$to}");
    }
}
