<?php

namespace Phaseolies\Console\Schedule;

use Cron\CronExpression;

class ScheduledCommand
{
    /**
     * The command string to be executed.
     *
     * @var string
     */
    private $command;

    /**
     * The CRON expression defining the schedule.
     * Defaults to every minute.
     *
     * @var string
     */
    private $expression = "* * * * *";

    /**
     * Indicates if the command should not overlap with itself.
     * When true, concurrent executions will be prevented.
     *
     * @var bool
     */
    public $withoutOverlapping = false;

    /**
     * Indicates if the command should run in the background.
     *
     * @var bool
     */
    private $runInBackground = false;

    /**
     * Path to a file used to store the timestamp of the last run.
     *
     * @var string
     */
    private $lastRunFile;

    /**
     * Path to the lock file used to prevent overlapping executions.
     *
     * @var string
     */
    private $lockFile;

    /**
     * Maximum duration in seconds that a lock should persist.
     * Default is 1440 seconds (24 minutes).
     *
     * @var int
     */
    private $maxLockTime = 1440;

    /**
     * Initializes the command with default lock and tracking file paths.
     *
     * @param string $command
     */
    public function __construct(string $command)
    {
        $this->command = $command;
        $this->lastRunFile = sys_get_temp_dir() . "/doppar_cron_" . md5($this->command);
        $this->lockFile = sys_get_temp_dir() . "/doppar_cron_lock_" . md5($this->command);
    }

    /**
     * Get the command string.
     *
     * @return string
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * Get the full path to the lock file.
     *
     * @return string
     */
    public function getLockFile(): string
    {
        return $this->lockFile;
    }

    /**
     * Schedule the command to run every minute.
     *
     * @return self
     */
    public function everyMinute(): self
    {
        return $this->cron("* * * * *");
    }

    /**
     * Schedule the command to run every five minutes.
     *
     * @return self
     */
    public function everyFiveMinutes(): self
    {
        return $this->cron("*/5 * * * *");
    }

    /**
     * Schedule the command to run every five minutes.
     *
     * @return self
     */
    public function everyTenMinutes(): self
    {
        return $this->cron("*/10 * * * *");
    }

    /**
     * Schedule the command to run every ten minutes.
     *
     * @return self
     */
    public function everyFifteenMinutes(): self
    {
        return $this->cron("*/15 * * * *");
    }

    /**
     * Schedule the command to run every thirty minutes.
     *
     * @return self
     */
    public function everyThirtyMinutes(): self
    {
        return $this->cron("*/30 * * * *");
    }

    /**
     * Schedule the command to run at the top of every hour.
     *
     * @return self
     */
    public function hourly(): self
    {
        return $this->cron("0 * * * *");
    }

    /**
     * Schedule the command to run daily at midnight.
     *
     * @return self
     */
    public function daily(): self
    {
        return $this->cron("0 0 * * *");
    }

    /**
     * Schedule the command to run daily at a specific time.
     *
     * @param string $time Format: "HH:MM"
     * @return self
     */
    public function dailyAt(string $time): self
    {
        $parts = explode(":", $time);
        $hour = $parts[0];
        $minute = $parts[1] ?? "0";

        return $this->cron("{$minute} {$hour} * * *");
    }

    /**
     * Schedule the command to run weekly on Sunday at midnight.
     *
     * @return self
     */
    public function weekly(): self
    {
        return $this->cron("0 0 * * 0");
    }

    /**
     * Schedule the command to run monthly on the 1st at midnight.
     *
     * @return self
     */
    public function monthly(): self
    {
        return $this->cron("0 0 1 * *");
    }

    /**
     * Schedule the command to run quarterly (every 3 months on the 1st at midnight).
     *
     * @return self
     */
    public function quarterly(): self
    {
        return $this->cron("0 0 1 */3 *");
    }

    /**
     * Schedule the command to run yearly on January 1st at midnight.
     *
     * @return self
     */
    public function yearly(): self
    {
        return $this->cron("0 0 1 1 *");
    }

    /**
     * Manually set a custom CRON expression.
     *
     * @param string $expression A valid CRON expression.
     * @return self
     */
    public function cron(string $expression): self
    {
        $this->expression = $expression;
        return $this;
    }

    /**
     * Prevent overlapping executions of the command.
     * Optionally, set a custom max lock time (in minutes).
     *
     * @param int $minutes The maximum lock time in minutes. Default: 1440 (24 hours)
     * @return self
     */
    public function noOverlap(int $minutes = 1440): self
    {
        $this->withoutOverlapping = true;
        $this->maxLockTime = $minutes;
        return $this;
    }

    /**
     * Mark the command to run in the background (non-blocking).
     *
     * @return self
     */
    public function inBackground(): self
    {
        $this->runInBackground = true;
        return $this;
    }

    /**
     * Check if the command is set to run in the background.
     *
     * @return bool True if background execution is enabled, false otherwise.
     */
    public function shouldRunInBackground(): bool
    {
        return $this->runInBackground;
    }

    /**
     * Determine if the command is due to run at the current time.
     * Uses the CRON expression and compares it against the current time.
     *
     * @return bool True if the command should be executed now, false otherwise.
     */
    public function isDue(): bool
    {
        // Check if command is due based on cron schedule
        $cron = new CronExpression($this->expression);
        if (!$cron->isDue()) {
            return false;
        }

        // Check for overlapping if enabled
        if ($this->withoutOverlapping) {
            $lockFile = $this->getLockFile();

            // Check if lock file exists and is still valid
            if (file_exists($lockFile)) {
                $lockTime = file_get_contents($lockFile);
                $lockDuration = $this->maxLockTime * 60; // Convert to seconds

                // If lock is still valid, skip execution
                if (time() - (int) $lockTime < $lockDuration) {
                    // Check if process is actually still running
                    $pidFile = $lockFile . ".pid";
                    if (file_exists($pidFile)) {
                        $processInfo = json_decode(
                            file_get_contents($pidFile),
                            true
                        );
                        if ($this->isProcessRunning($processInfo["pid"] ?? 0)) {
                            return false;
                        }
                    }

                    // Process isn't running but lock exists - clean up
                    $this->releaseLock();
                }
            }

            // Create new lock
            $this->lock();
        }

        return true;
    }

    /**
     * Check if a process with the given PID is currently running.
     *
     * @param int $pid The process ID to check.
     * @return bool True if the process is running, false otherwise.
     */
    private function isProcessRunning(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        try {
            $output = shell_exec(sprintf("ps -p %d -o pid=", $pid));
            return !empty($output);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Determine if the command is currently locked (i.e., should not run again yet).
     *
     * This checks for the existence of the lock file and ensures it is still valid
     * based on the configured maximum lock duration.
     *
     * @return bool True if the command is considered locked, false otherwise.
     */
    public function isLocked(): bool
    {
        if (!file_exists($this->lockFile)) {
            return false;
        }

        $lockTime = file_get_contents($this->lockFile);
        return time() - (int) $lockTime < $this->maxLockTime * 60;
    }

    /**
     * Create or update the lock file with the current timestamp.
     *
     * This prevents overlapping executions by marking the command as "in progress".
     *
     * @return void
     */
    public function lock(): void
    {
        file_put_contents($this->lockFile, time());
    }

    /**
     * Remove the lock file, allowing the command to be run again.
     *
     * @return void
     */
    public function releaseLock(): void
    {
        if (file_exists($this->lockFile)) {
            unlink($this->lockFile);
        }
    }

    /**
     * Automatically release the lock if the command is not running in the background
     * and is set to prevent overlapping executions.
     */
    public function __destruct()
    {
        if (!$this->runInBackground && $this->withoutOverlapping) {
            $this->releaseLock();
        }
    }
}
