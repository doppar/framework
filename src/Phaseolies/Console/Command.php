<?php

namespace Phaseolies\Console;

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

        $userDefineCommandsDir = base_path('app/Schedule/Commands');
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
            $commands[] = $this->app->make($command);
        }

        $console->addCommands($commands);
    }
}
