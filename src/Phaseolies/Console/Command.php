<?php

namespace Phaseolies\Console;

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

        $commands = [];
        foreach ($commandClasses as $command) {
            $commands[] = $this->app->make($command);
        }

        $console->addCommands($commands);
    }
}
