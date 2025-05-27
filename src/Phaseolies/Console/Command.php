<?php

namespace Phaseolies\Console;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Phaseolies\Application;

class Command extends Console
{
    /**
     * The application instance.
     */
    protected Application $app;

    public function __construct(Application $app)
    {
        parent::__construct($app);
        $this->app = $app;
    }

    /**
     * Register all the application commands
     *
     * @param Console $consol
     * @return void
     */
    public function registerCommands(Console $console): void
    {
        $commandsDir = __DIR__ . '/Commands';
        $commandFiles = glob($commandsDir . '/*.php');

        $commandClasses = array_map(
            fn($file) => 'Phaseolies\\Console\\Commands\\' . basename($file, '.php'),
            $commandFiles
        );

        $files = [];
        $userDefineCommandsDir = base_path('app/Schedule/Commands');
        $dirIterator = new RecursiveDirectoryIterator($userDefineCommandsDir);
        $iterator = new RecursiveIteratorIterator($dirIterator);

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        $files = array_map(function ($file) use ($userDefineCommandsDir) {
            $relativePath = str_replace([$userDefineCommandsDir, '.php', '/'], ['', '', '\\'], $file);
            return 'App\\Schedule\\Commands' . $relativePath;
        }, $files);

        $commandClasses = array_merge($files, $commandClasses);

        $commands = [];
        foreach ($commandClasses as $command) {
            $commands[] = $this->app->make($command);
        }

        $console->addCommands($commands);
    }
}
