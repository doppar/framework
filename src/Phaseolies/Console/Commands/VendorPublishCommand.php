<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Phaseolies\Application;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;

class VendorPublishCommand extends Command
{
    protected static $defaultName = 'vendor:publish';

    protected Application $app;

    public function __construct()
    {
        parent::__construct();
        $this->app = app();
    }

    protected function configure()
    {
        $this->setName('vendor:publish')
            ->setDescription('Publish any publishable assets from vendor packages')
            ->addOption(
                'provider',
                null,
                InputOption::VALUE_OPTIONAL,
                'The service provider that has assets you want to publish'
            )
            ->addOption(
                'tag',
                null,
                InputOption::VALUE_OPTIONAL,
                'One or many tags that have assets you want to publish'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Overwrite any existing files'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $provider = $input->getOption('provider');
        $tag = $input->getOption('tag');
        $force = $input->getOption('force');

        if ($provider) {
            $this->publishProvider($provider, $output, $force);
            return Command::SUCCESS;
        }

        if ($tag) {
            $this->publishTag($tag, $output, $force);
            return Command::SUCCESS;
        }

        $this->publishAll($output, $force);

        return Command::SUCCESS;
    }

    protected function publishProvider(string $provider, OutputInterface $output, bool $force = false)
    {
        $providerClass = $this->app->getProvider($provider);

        if (!$providerClass) {
            $output->writeln("<error>Unable to locate provider: {$provider}</error>");
            return;
        }

        $paths = $providerClass->pathsToPublish($provider);

        $this->publishPaths($paths, $output, $force);
    }

    protected function publishTag(string $tag, OutputInterface $output, bool $force = false)
    {
        $paths = [];

        foreach ($this->app->getProviders() as $provider) {
            $providerPaths = $provider->pathsToPublish(null, $tag);
            if (!empty($providerPaths)) {
                $paths = array_merge($paths, $providerPaths);
            }
        }

        if (empty($paths)) {
            $output->writeln("<error>Unable to locate tag: {$tag}</error>");
            return;
        }

        $this->publishPaths($paths, $output, $force);
    }

    protected function publishAll(OutputInterface $output, bool $force = false)
    {
        foreach ($this->app->getProviders() as $provider) {
            $paths = $provider->pathsToPublish();
            $this->publishPaths($paths, $output, $force);
        }
    }

    protected function publishPaths(array $paths, OutputInterface $output, bool $force = false)
    {
        foreach ($paths as $from => $to) {
            if (is_dir($from)) {
                $this->publishDirectory($from, $to, $output, $force);
            } else {
                $this->publishFile($from, $to, $output, $force);
            }
        }
    }

    protected function publishDirectory(string $from, string $to, OutputInterface $output, bool $force = false)
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
                $this->publishFile($item->getPathname(), $target, $output, $force);
            }
        }
    }

    protected function publishFile(string $from, string $to, OutputInterface $output, bool $force = false)
    {
        if (file_exists($to) && !$force) {
            $output->writeln("<error>Skipping: File already exists </error>");
            return;
        }

        $directory = dirname($to);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        copy($from, $to);
        $output->writeln("<info>Copied:</info> {$from} <info>to</info> {$to}");
    }
}
