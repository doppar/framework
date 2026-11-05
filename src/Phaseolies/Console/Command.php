<?php

namespace Phaseolies\Console;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Phaseolies\Application;

class Command extends Console
{
    /**
     * The application instance.
     *
     * @var Application
     */
    protected Application $app;

    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        parent::__construct($app);

        $this->app = $app;
    }

    /**
     * Register all the application commands
     *
     * @param Console $console
     * @return void
     */
    public function registerCommands(Console $console): void
    {
        $commandsDir = __DIR__ . '/Commands';
        $commandFiles = [];

        if (is_dir($commandsDir)) {
            $dirIterator = new RecursiveDirectoryIterator($commandsDir);
            $iterator = new RecursiveIteratorIterator($dirIterator);

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $commandFiles[] = $file->getPathname();
                }
            }
        }

        $commandClasses = array_map(function ($file) use ($commandsDir) {
            $relativePath = str_replace([$commandsDir, '.php', '/'], ['', '', '\\'], $file);
            return 'Phaseolies\\Console\\Commands' . $relativePath;
        }, $commandFiles);

        $userDefineCommandsDir = base_path('src/Schedule/Commands');
        if (is_dir($userDefineCommandsDir)) {
            $files = [];
            $dirIterator = new RecursiveDirectoryIterator($userDefineCommandsDir);
            $iterator = new RecursiveIteratorIterator($dirIterator);

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }

            $userCommands = array_map(function ($file) use ($userDefineCommandsDir) {
                $relativePath = str_replace([$userDefineCommandsDir, '.php', '/'], ['', '', '\\'], $file);
                return 'App\\Schedule\\Commands' . $relativePath;
            }, $files);

            $commandClasses = array_merge($commandClasses, $userCommands);
        }

        $commands = [];
        foreach ($commandClasses as $command) {
            // One broken command must not take every other `pool` command down with it.
            try {
                if (!$this->isCommandClass($command)) {
                    continue;
                }

                $commands[] = $this->app->make($command);
            } catch (\Throwable $e) {
                $this->reportSkippedCommand($command, $e);
            }
        }

        $console->addCommands($commands);
    }

    /**
     * Tell the user a command could not be loaded.
     *
     * @param string $class
     * @param \Throwable $e
     * @return void
     */
    protected function reportSkippedCommand(string $class, \Throwable $e): void
    {
        fwrite(STDERR, sprintf("Skipped command [%s]: %s\n", $class, $e->getMessage()));
    }

    /**
     * Whether a discovered class is a concrete console command. Helpers, traits,
     * interfaces and abstract base classes that live next to commands are ignored.
     *
     * @param string $class
     * @return bool
     */
    protected function isCommandClass(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }

        return is_subclass_of($class, SymfonyCommand::class)
            && !(new \ReflectionClass($class))->isAbstract();
    }
}
