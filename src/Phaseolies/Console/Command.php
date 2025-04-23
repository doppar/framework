<?php

namespace Phaseolies\Console;

class Command extends Console
{
    /**
     * The application instance.
     */
    protected \Phaseolies\Application $app;

    public function __construct(\Phaseolies\Application $app)
    {
        parent::__construct($app);
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

        $commands = [];
        foreach ($commandFiles as $commandFile) {
            $commandClass = 'Phaseolies\Console\Commands\\' . basename($commandFile, '.php');
            $commands[] = new $commandClass();
        }

        $console->addCommands($commands);
    }
}
