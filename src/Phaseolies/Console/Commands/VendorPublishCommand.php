<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Application;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;

class VendorPublishCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'vendor:publish {--provider=} {--tag=} {--force}';

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
    protected function handle(): int
    {
        return $this->executeWithTiming(function () {
            $provider = $this->option('provider');
            $tag = $this->option('tag');
            $force = $this->option('force');

            if ($provider) {
                $this->publishProvider($provider, $force);
                return 0;
            }

            if ($tag) {
                $this->publishTag($tag, $force);
                return 0;
            }

            $this->publishAll($force);
            return 0;
        });
    }

    protected function publishProvider(string $provider, bool $force = false)
    {
        $providerClass = $this->app->getProvider($provider);

        if (!$providerClass) {
            $this->displayError("Unable to locate provider: {$provider}");
            return;
        }

        $paths = $providerClass->pathsToPublish($provider);
        $this->publishPaths($paths, $force);

        $this->newLine();
        $this->displaySuccess("Published assets for provider: {$provider}");
    }

    protected function publishTag(string $tag, bool $force = false)
    {
        $paths = [];

        foreach ($this->app->getProviders() as $provider) {
            $providerPaths = $provider->pathsToPublish(null, $tag);
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
        foreach ($this->app->getProviders() as $provider) {
            $paths = $provider->pathsToPublish();
            if (!empty($paths)) {
                $this->publishPaths($paths, $force);
                $publishedCount++;
            }
        }

        $this->newLine();
        $this->displaySuccess("Published assets from {$publishedCount} providers");
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
