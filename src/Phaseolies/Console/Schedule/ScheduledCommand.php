<?php

namespace Phaseolies\Console\Schedule;

use DateTimeZone;
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
     * The timezone the command should run in.
     *
     * @var string|null
     */
    private $timezone = null;

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
     * Schedule the command to run every two minutes.
     *
     * @return self
     */
    public function everyTwoMinutes(): self
    {
        return $this->cron("*/2 * * * *");
    }

    /**
     * Schedule the command to run every three minutes.
     *
     * @return self
     */
    public function everyThreeMinutes(): self
    {
        return $this->cron("*/3 * * * *");
    }

    /**
     * Schedule the command to run every four minutes.
     *
     * @return self
     */
    public function everyFourMinutes(): self
    {
        return $this->cron("*/4 * * * *");
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
     * Schedule the command to run every fifteen minutes.
     *
     * @return self
     */
    public function everyFifteenMinutes(): self
    {
        return $this->cron("*/15 * * * *");
    }

    /**
     * Schedule the command to run every twenty minutes.
     *
     * @return self
     */
    public function everyTwentyMinutes(): self
    {
        return $this->cron("*/20 * * * *");
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
     * Schedule the command to run hourly at a specific minute.
     *
     * @param int $minute The minute of the hour (0-59)
     * @return self
     */
    public function hourlyAt(int $minute): self
    {
        return $this->cron("{$minute} * * * *");
    }

    /**
     * Schedule the command to run every odd hour.
     *
     * @param int $minutes The minutes past the hour (0-59)
     * @return self
     */
    public function everyOddHour(int $minutes = 0): self
    {
        return $this->cron("{$minutes} 1-23/2 * * *");
    }

    /**
     * Schedule the command to run every two hours.
     *
     * @param int $minutes The minutes past the hour (0-59)
     * @return self
     */
    public function everyTwoHours(int $minutes = 0): self
    {
        return $this->cron("{$minutes} */2 * * *");
    }

    /**
     * Schedule the command to run every four hours.
     *
     * @param int $minutes The minutes past the hour (0-59)
     * @return self
     */
    public function everyFourHours(int $minutes = 0): self
    {
        return $this->cron("{$minutes} */4 * * *");
    }

    /**
     * Schedule the command to run every six hours.
     *
     * @param int $minutes The minutes past the hour (0-59)
     * @return self
     */
    public function everySixHours(int $minutes = 0): self
    {
        return $this->cron("{$minutes} */6 * * *");
    }

    /**
     * Schedule the command to run twice daily at specified hours.
     *
     * @param int $firstHour First hour (0-23)
     * @param int $secondHour Second hour (0-23)
     * @return self
     */
    public function twiceDaily(int $firstHour, int $secondHour): self
    {
        return $this->cron("0 {$firstHour},{$secondHour} * * *");
    }

    /**
     * Schedule the command to run twice daily at specified hours and minute.
     *
     * @param int $firstHour First hour (0-23)
     * @param int $secondHour Second hour (0-23)
     * @param int $minute The minute of the hour (0-59)
     * @return self
     */
    public function twiceDailyAt(int $firstHour, int $secondHour, int $minute): self
    {
        return $this->cron("{$minute} {$firstHour},{$secondHour} * * *");
    }

    /**
     * Schedule the command to run weekly on a specific day and time.
     *
     * @param int $day Day of week (0-6, 0 = Sunday)
     * @param string $time Time in "HH:MM" format
     * @return self
     */
    public function weeklyOn(int $day, string $time): self
    {
        $parts = explode(':', $time);
        $hour = $parts[0];
        $minute = $parts[1] ?? '0';

        return $this->cron("{$minute} {$hour} * * {$day}");
    }

    /**
     * Schedule the command to run monthly on a specific day and time.
     *
     * @param int $day Day of month (1-31)
     * @param string $time Time in "HH:MM" format
     * @return self
     */
    public function monthlyOn(int $day, string $time): self
    {
        $parts = explode(':', $time);
        $hour = $parts[0];
        $minute = $parts[1] ?? '0';

        return $this->cron("{$minute} {$hour} {$day} * *");
    }

    /**
     * Schedule the command to run twice monthly on specific days and time.
     *
     * @param int $firstDay First day of month (1-31)
     * @param int $secondDay Second day of month (1-31)
     * @param string $time Time in "HH:MM" format
     * @return self
     */
    public function twiceMonthly(int $firstDay, int $secondDay, string $time): self
    {
        $parts = explode(':', $time);
        $hour = $parts[0];
        $minute = $parts[1] ?? '0';

        return $this->cron("{$minute} {$hour} {$firstDay},{$secondDay} * *");
    }

    /**
     * Schedule the command to run on the last day of the month at a specific time.
     *
     * @param string $time Time in "HH:MM" format
     * @return self
     */
    public function lastDayOfMonth(string $time): self
    {
        $parts = explode(':', $time);
        $hour = $parts[0];
        $minute = $parts[1] ?? '0';

        return $this->cron("{$minute} {$hour} L * *");
    }

    /**
     * Restrict the command to run only between specific hours.
     *
     * @param string $startTime Start time in "HH:MM" format
     * @param string $endTime End time in "HH:MM" format
     * @return self
     */
    public function between(string $startTime, string $endTime): self
    {
        $start = explode(':', $startTime);
        $end = explode(':', $endTime);

        $startHour = (int)$start[0];
        $startMinute = $start[1] ?? '0';
        $endHour = (int)$end[0];
        $endMinute = $end[1] ?? '0';

        if ($endHour < $startHour || ($endHour == $startHour && $endMinute < $startMinute)) {
            $part1 = $this->createBetweenExpression($startHour, $startMinute, 23, 59);
            $part2 = $this->createBetweenExpression(0, 0, $endHour, $endMinute);

            $this->expression = "{$this->expression} && ({$part1} || {$part2})";
        } else {
            $expression = $this->createBetweenExpression($startHour, $startMinute, $endHour, $endMinute);
            $this->expression = "{$this->expression} && {$expression}";
        }

        return $this;
    }

    /**
     * Helper method to create a between expression for cron.
     *
     * @param int $startHour
     * @param string $startMinute
     * @param int $endHour
     * @param string $endMinute
     * @return string
     */
    private function createBetweenExpression(int $startHour, string $startMinute, int $endHour, string $endMinute): string
    {
        if ($startHour == $endHour) {
            return sprintf(
                '%d %d-%d * * *',
                $startMinute,
                $startHour,
                $endHour
            );
        }

        return sprintf(
            '%d-%d %d-%d * * *',
            $startMinute,
            $endMinute,
            $startHour,
            $endHour
        );
    }

    /**
     * Set the timezone for the command's schedule.
     *
     * @param string $timezone Valid PHP timezone identifier
     * @return self
     * @throws \Exception if the timezone is invalid
     */
    public function timezone(string $timezone): self
    {
        if (!in_array($timezone, DateTimeZone::listIdentifiers())) {
            throw new \InvalidArgumentException("Invalid timezone '{$timezone}'");
        }

        $this->timezone = $timezone;
        return $this;
    }

    /**
     * Get the timezone for this command.
     *
     * @return string|null
     */
    public function getTimezone(): ?string
    {
        return $this->timezone;
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
        $now = new \DateTime('now', $this->timezone ? new DateTimeZone($this->timezone) : config('app.timezone'));

        // Check if command is due based on cron schedule
        $cron = new CronExpression($this->expression);
        if (!$cron->isDue($now)) {
            return false;
        }

        // Check for overlapping if enabled
        if ($this->withoutOverlapping) {
            $lockFile = $this->getLockFile();
            $pidFile = $lockFile . '.pid';

            if (file_exists($pidFile)) {
                $processInfo = json_decode(file_get_contents($pidFile), true);
                if (!$this->isProcessRunning($processInfo['pid'] ?? 0)) {
                    $this->releaseLock();
                }
            }

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

        $pidFile = $this->lockFile . '.pid';
        if (file_exists($pidFile)) {
            unlink($pidFile);
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
