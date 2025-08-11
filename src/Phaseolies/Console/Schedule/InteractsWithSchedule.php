<?php

namespace Phaseolies\Console\Schedule;

use Phaseolies\Console\Schedule\ScheduledCommand;

trait InteractsWithSchedule
{
    /**
     * Holds all scheduled commands registered with the scheduler.
     *
     * Each element in the array is an instance of {@see ScheduledCommand},
     * representing a command that has been scheduled to run at a specified time.
     *
     * @var ScheduledCommand[]
     */
    private $commands = [];

    /**
     * Schedule a new command.
     *
     * @param string $command
     * @return ScheduledCommand
     */
    public function command(string $command): ScheduledCommand
    {
        $scheduledCommand = new ScheduledCommand($command);
        $this->commands[] = $scheduledCommand;

        return $scheduledCommand;
    }

    /**
     * Get all registered scheduled commands
     *
     * @return array
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Retrieve only the commands that are currently due to run.
     *
     * @return array
     */
    public function dueCommands(): array
    {
        return array_filter($this->commands, fn(ScheduledCommand $command) => $command->isDue());
    }
}
