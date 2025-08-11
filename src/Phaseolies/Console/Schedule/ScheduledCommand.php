<?php

namespace Phaseolies\Console\Schedule;

use DateTimeZone;
use Cron\CronExpression;
use Carbon\Carbon;

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
     * Dates on which the command should not run.
     *
     * @var array
     */
    private $excludedDates = [];

    /**
     * Additional conditions that may affect the execution of the command.
     *
     * @var array
     */
    private $additionalConditions = [];

    /**
     * The rate limit value for the command, if applicable.
     *
     * @var int|null
     */
    private $rateLimit = null;

    /**
     * The condition that might be applied on cron expression
     *
     * @var callable
     */
    private $condition = null;

    /**
     * The callback to execute when the command succeeds.
     *
     * @var callable|null
     */
    private $onSuccessCallback = null;

    /**
     * The callback to execute when the command fails.
     *
     * @var callable|null
     */
    private $onFailureCallback = null;

    /**
     * The number of times the command has been attempted.
     *
     * @var int
     */
    private $attempts = 0;

    /**
     * The maximum number of retry attempts.
     *
     * @var int
     */
    private $maxRetries = 0;

    /**
     * The delay between retry attempts in seconds.
     *
     * @var int
     */
    private $retryDelay = 60;

    /**
     * Initializes the command with default lock and tracking file paths.
     *
     * @param string $command
     */
    public function __construct(string $command)
    {
        if (empty(trim($command))) {
            throw new \InvalidArgumentException('Command cannot be empty');
        }

        $this->command = $command;
        $tempDir = sys_get_temp_dir();
        $this->lastRunFile = $tempDir . "/doppar_cron_" . md5($this->command);
        $this->lockFile = $tempDir . "/doppar_cron_lock_" . md5($this->command);

        $this->ensureSecureFilePermissions($this->lastRunFile);
        $this->ensureSecureFilePermissions($this->lockFile);
    }

    /**
     * Ensure secure file permissions for lock files.
     *
     * @param string $filePath Path to the file
     */
    private function ensureSecureFilePermissions(string $filePath): void
    {
        if (file_exists($filePath)) {
            chmod($filePath, 0600);
        }
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
        if (
            !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $startTime) ||
            !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $endTime)
        ) {
            throw new \InvalidArgumentException('Time must be in "HH:MM" format');
        }

        $this->addTimeCondition(function () use ($startTime, $endTime) {
            $timezone = $this->timezone ?: config('app.timezone', 'UTC');
            $now = Carbon::now($timezone);

            // Extract hour and minute
            [$startHour, $startMinute] = explode(':', $startTime);
            [$endHour, $endMinute] = explode(':', $endTime);

            // Set start and end to today with the correct times
            $start = $now->copy()->setTime($startHour, $startMinute);
            $end = $now->copy()->setTime($endHour, $endMinute);

            if ($start->lt($end)) {
                // Normal range (e.g., 15:00 to 17:00)
                return $now->between($start, $end);
            }

            // Overnight range (e.g., 23:00 to 02:00)
            return $now->gte($start) || $now->lte($end);
        });

        return $this;
    }

    /**
     * Adding time condition
     *
     * @param callable $condition
     * @return void
     */
    private function addTimeCondition(callable $condition): void
    {
        if (!isset($this->additionalConditions)) {
            $this->additionalConditions = [];
        }

        $this->additionalConditions[] = $condition;
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
     * Add dates when the command should NOT run (e.g., holidays).
     *
     * @param array|string $dates Format: "YYYY-MM-DD" or ["YYYY-MM-DD", ...]
     */
    public function exclude(array|string ...$dates): self
    {
        $dates = count($dates) === 1 && is_array($dates[0])
            ? $dates[0]
            : $dates;

        $this->excludedDates = array_merge($this->excludedDates, $dates);

        return $this;
    }

    /**
     * Set max executions per time window (e.g., "5/1h" for 5 runs per hour)
     *
     * @param string $limit Format: "COUNT/TIME" (e.g., "10/1h", "1/30m")
     * @return self
     * @throws \InvalidArgumentException if the format is invalid
     */
    public function throttle(string $limit): self
    {
        if (!preg_match('/^\d+\/[1-9]\d*[smhd]$/', $limit)) {
            throw new \InvalidArgumentException(
                'Invalid throttle format. Expected "attempts/interval" where interval is like 1s, 5m, 2h, or 1d'
            );
        }

        $this->rateLimit = $limit;

        return $this;
    }

    /**
     * Checking if the cron within the ratelimit range
     *
     * @return bool
     */
    private function isWithinRateLimit(): bool
    {
        if (!$this->rateLimit) return true;

        $parts = explode('/', $this->rateLimit);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Invalid rate limit format');
        }

        $maxAttempts = (int)$parts[0];
        $interval = strtolower(trim($parts[1]));

        $unit = substr($interval, -1);
        $value = (int)substr($interval, 0, -1);

        $decaySeconds = match ($unit) {
            's' => $value,
            'm' => $value * 60,
            'h' => $value * 3600,
            'd' => $value * 86400,
            default => throw new \InvalidArgumentException('Invalid time unit')
        };

        $logFile = storage_path('schedule/cron_throttle_' . md5($this->command) . '.log');

        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }

        // Use file locking to prevent race conditions
        $fp = fopen($logFile, 'c+');
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return false;
        }

        $runs = [];
        if (filesize($logFile) > 0) {
            $runs = json_decode(fread($fp, filesize($logFile)), true) ?: [];
        }

        // Filter out expired runs
        $currentTime = time();
        $runs = array_filter($runs, fn($time) => $currentTime - $time < $decaySeconds);

        if (count($runs) >= $maxAttempts) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return false;
        }

        // Add current run
        $runs[] = $currentTime;

        // Write back to file
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($runs));
        flock($fp, LOCK_UN);
        fclose($fp);

        return true;
    }

    /**
     * Set a condition closure. Command runs only if it returns `true`.
     *
     * @param callable $condition
     */
    public function when(callable $condition): self
    {
        $this->condition = $condition;

        return $this;
    }

    /**
     * Set a callback to execute when the command succeeds.
     *
     * @param callable $callback Receives the command output as parameter
     * @return self
     */
    public function onSuccess(callable $callback): self
    {
        $this->onSuccessCallback = $callback;

        return $this;
    }

    /**
     * Set a callback to execute when the command fails.
     *
     * @param callable $callback Receives the exception and attempt count as parameters
     * @return self
     */
    public function onFailure(callable $callback): self
    {
        $this->onFailureCallback = $callback;

        return $this;
    }

    /**
     * Execute the command with proper sanitization and callback handling.
     *
     * @return mixed The command output or true if running in background
     * @throws \Exception If the command fails and no retries are left
     */
    public function run()
    {
        try {
            $command = $this->sanitizeCommand($this->command);

            if ($this->shouldRunInBackground()) {
                $command = $command . ' > /dev/null 2>&1 &';
                exec($command, $output, $returnVar);

                if ($returnVar !== 0) {
                    throw new \RuntimeException("Command failed to start in background");
                }

                $result = true;
            } else {
                exec($command, $output, $returnVar);

                if ($returnVar !== 0) {
                    throw new \RuntimeException("Command failed with exit code: {$returnVar}");
                }

                $result = implode("\n", $output);
            }

            if ($this->onSuccessCallback) {
                call_user_func($this->onSuccessCallback, $result);
            }

            return $result;
        } catch (\Exception $e) {
            if ($this->onFailureCallback) {
                call_user_func($this->onFailureCallback, $e, $this->attempts + 1);
            }
            throw $e;
        }
    }

    /**
     * Basic command sanitization to prevent command injection.
     *
     * @param string $command The command to sanitize
     * @return string The sanitized command
     */
    private function sanitizeCommand(string $command): string
    {
        $command = escapeshellcmd($command);

        $command = str_replace("\0", '', $command);

        return $command;
    }

    /**
     * Execute the command with retry logic and proper cleanup.
     *
     * @return mixed The command output or true if running in background
     * @throws \Exception If all retry attempts are exhausted
     */
    public function runWithRetry()
    {
        $this->attempts = 0;
        $lastException = null;
        $maxRetries = $this->maxRetries;

        while ($this->attempts <= $maxRetries) {
            try {
                $this->attempts++;
                $result = $this->run();

                // Reset attempts on success
                $this->attempts = 0;
                return $result;
            } catch (\Exception $e) {
                $lastException = $e;

                if ($this->onFailureCallback) {
                    call_user_func($this->onFailureCallback, $e, $this->attempts);
                }

                if ($this->attempts <= $maxRetries) {
                    sleep($this->retryDelay);
                } else {
                    // Ensure cleanup on final failure
                    $this->attempts = 0;
                    if ($this->withoutOverlapping) {
                        $this->releaseLock();
                    }
                }
            }
        }

        throw new \Exception(
            "Command failed after {$maxRetries} attempts. Last error: " .
                $lastException->getMessage(),
            0,
            $lastException
        );
    }

    /**
     * Set max retry attempts and delay between retries.
     *
     * @param int $retries Maximum number of retry attempts
     * @param int $delaySeconds Delay between retries in seconds
     * @return self
     */
    public function retry(int $retries, int $delaySeconds = 60): self
    {
        $this->maxRetries = $retries;

        $this->retryDelay = $delaySeconds;

        return $this;
    }

    /**
     * Determine if the command is due to run with comprehensive checks.
     *
     * @return bool True if the command should be executed now, false otherwise.
     * @throws \InvalidArgumentException if cron expression is invalid
     */
    public function isDue(): bool
    {
        try {
            $timezone = $this->timezone ?: config('app.timezone', 'UTC');
            $now = now($timezone);

            // Check excluded dates
            if (in_array($now->format('Y-m-d'), $this->excludedDates)) {
                if ($this->onSuccessCallback) {
                    call_user_func($this->onSuccessCallback, 'Skipped due to excluded date');
                }
                return false;
            }

            // Check additional conditions (including time conditions from between())
            if (!empty($this->additionalConditions)) {
                foreach ($this->additionalConditions as $condition) {
                    if (!call_user_func($condition)) {
                        return false;
                    }
                }
            }

            // Check custom condition
            if ($this->condition && !call_user_func($this->condition)) {
                if ($this->onSuccessCallback) {
                    call_user_func($this->onSuccessCallback, 'Skipped due to custom condition');
                }
                return false;
            }

            // Validate cron expression
            if (!CronExpression::isValidExpression($this->expression)) {
                throw new \InvalidArgumentException("Invalid CRON expression: {$this->expression}");
            }

            // Check cron schedule
            $cron = new CronExpression($this->expression);
            if (!$cron->isDue($now, $timezone)) {
                if ($this->onSuccessCallback) {
                    call_user_func($this->onSuccessCallback, 'Not due according to schedule');
                }
                return false;
            }

            // Check rate limiting
            if (!$this->isWithinRateLimit()) {
                if ($this->onSuccessCallback) {
                    call_user_func($this->onSuccessCallback, 'Rate limit exceeded');
                }
                return false;
            }

            // Handle overlapping prevention
            if ($this->withoutOverlapping) {
                $canRun = $this->handleOverlappingPrevention();
                if (!$canRun && $this->onSuccessCallback) {
                    call_user_func($this->onSuccessCallback, 'Skipped due to overlapping prevention');
                }
                return $canRun;
            }

            if ($this->onSuccessCallback) {
                call_user_func($this->onSuccessCallback, 'Command is due to run');
            }
            return true;
        } catch (\Exception $e) {
            if ($this->onFailureCallback) {
                call_user_func($this->onFailureCallback, $e, 0);
            }
            return false;
        }
    }

    /**
     * Handle the overlapping prevention logic.
     *
     * @return bool True if command can run (no overlap), false otherwise
     */
    private function handleOverlappingPrevention(): bool
    {
        $lockFile = $this->getLockFile();
        $pidFile = $lockFile . '.pid';

        // Clean up stale locks
        if (file_exists($pidFile)) {
            $processInfo = @json_decode(file_get_contents($pidFile), true);
            if (!$this->isProcessRunning($processInfo['pid'] ?? 0)) {
                $this->releaseLock();
                if ($this->onSuccessCallback) {
                    call_user_func($this->onSuccessCallback, 'Cleaned up stale lock');
                }
            }
        }

        // Check if lock exists and is valid
        if (file_exists($lockFile)) {
            $lockTime = @file_get_contents($lockFile);
            $lockDuration = $this->maxLockTime * 60;

            if (time() - (int)$lockTime < $lockDuration) {
                if (file_exists($pidFile)) {
                    $processInfo = @json_decode(file_get_contents($pidFile), true);
                    if ($this->isProcessRunning($processInfo['pid'] ?? 0)) {
                        return false;
                    }
                }
                $this->releaseLock();
                if ($this->onSuccessCallback) {
                    call_user_func($this->onSuccessCallback, 'Released expired lock');
                }
            }
        }

        // Create new lock with process ID
        $this->lock();

        file_put_contents($pidFile, json_encode(['pid' => getmypid()]));

        if ($this->onSuccessCallback) {
            call_user_func($this->onSuccessCallback, 'Lock acquired, command can run');
        }

        return true;
    }

    /**
     * Clean up all lock files, including those from background processes.
     *
     * @return void
     */
    public function cleanup(): void
    {
        $this->releaseLock();

        $throttleFile = $this->lastRunFile . '_throttle.log';

        if (file_exists($throttleFile)) {
            unlink($throttleFile);
        }
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
        if ($this->withoutOverlapping) {
            $this->cleanup();
        }
    }
}
