<?php

namespace Phaseolies\Console;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Console extends Application
{
    /**
     * The application instance.
     */
    protected \Phaseolies\Application $app;

    /**
     * Create a new Console instance.
     *
     * @param \Phaseolies\Application $app
     * @param mixed string
     * @param string $version
     */
    public function __construct(\Phaseolies\Application $app, string $name = 'Phaseolies Framework', string $version = '1.0.0')
    {
        parent::__construct($name, $version);
        $this->app = $app;
    }

    /**
     * Add multiple commands at once.
     *
     * @param array $commands
     * @return void
     */
    public function addCommands(array $commands): void
    {
        foreach ($commands as $command) {
            $this->add($this->resolveCommand($command));
        }
    }

    /**
     * Resolve a command instance.
     *
     * @param string|Command $command
     * @return Command
     */
    protected function resolveCommand(string|Command $command): Command
    {
        if (is_string($command)) {
            $command = $this->app->make($command);
        }

        return $command;
    }

    /**
     * Run the console application.
     *
     * @param InputInterface|null $input
     * @param OutputInterface|null $output
     * @return int
     */
    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        $this->bootstrapCommands();

        return parent::run($input, $output);
    }

    /**
     * Bootstrap all registered commands.
     */
    protected function bootstrapCommands(): void
    {
        $command = new \Phaseolies\Console\Command($this->app);

        $command->registerCommands($this);
    }
}
