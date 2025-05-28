<?php

namespace Phaseolies\Console\Schedule;

use Phaseolies\Console\Schedule\ScheduledCommand;

trait InteractsWithSchedule
{
    /**
     * Array to store scheduled commands
     *
     * @var array
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
     * Retrieve only the commands that are currently due to run.
     *
     * @return array
     */
    public function dueCommands(): array
    {
        return array_filter($this->commands, function (
            ScheduledCommand $command
        ) {
            return $command->isDue();
        });
    }

    /**
     * Remove old lock files for stale or expired scheduled command executions.
     * Lock files are used to prevent duplicate executions. This method
     * cleans up any lock files older than 24 hours (86400 seconds)
     *
     * @return void
     */
    public function cleanupStaleLocks(): void
    {
        foreach (glob(sys_get_temp_dir() . '/doppar_cron_lock_*') as $file) {
            if (!is_file($file)) continue;

            $lockTime = @file_get_contents($file);
            if ($lockTime && time() - $lockTime > 86400) {
                @unlink($file);
                $pidFile = $file . '.pid';
                if (file_exists($pidFile)) {
                    @unlink($pidFile);
                }
            }
        }
    }
}
