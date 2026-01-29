<?php

namespace Phaseolies\Console\Commands\Cron;

use App\Schedule\Schedule;
use Phaseolies\Console\Schedule\Command;
use Symfony\Component\Process\Process;
use React\EventLoop\Loop;

class CronRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'cron:run {--daemon : Run in daemon mode for second-based schedules}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Run the scheduled commands';

    /**
     * Track last execution times for second-based commands (timestamp in seconds)
     *
     * @var array
     */
    protected $lastExecution = [];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $isDaemon = $this->option('daemon');

        if ($isDaemon) {
            return $this->runDaemonMode();
        }

        return $this->runStandardMode();
    }

    /**
     * Run in standard mode (called by system cron every minute)
     *
     * @return int
     */
    protected function runStandardMode(): int
    {
        return $this->executeWithTiming(function () {
            $schedule = new Schedule();
            $schedule->schedule($schedule);

            $allCommands = $schedule->getCommands();

            $secondBasedCommands = [];
            $regularCommands = [];

            foreach ($allCommands as $command) {
                if ($command->isSecondSchedule()) {
                    $secondBasedCommands[] = $command;
                } else {
                    $regularCommands[] = $command;
                }
            }

            // Run regular commands
            $regularDueCommands = array_filter($regularCommands, fn($cmd) => $cmd->isDue());

            foreach ($regularDueCommands as $command) {
                $this->executeCommand($command);
            }

            if (!empty($secondBasedCommands)) {
                $this->displayInfo('Found ' . count($secondBasedCommands) . ' second-based schedule(s)');

                // Check if daemon is running
                if (!$this->isDaemonRunning()) {
                    $this->displayWarning('Second-based schedules detected but daemon is not running!');
                    $this->displayInfo('Start the daemon with: php pool cron:run --daemon');
                }
            }

            $totalExecuted = count($regularDueCommands);

            if ($totalExecuted > 0) {
                $this->newLine(1);
                $this->displaySuccess('Executed ' . $totalExecuted . ' scheduled command(s)');
            } else {
                $this->displayInfo('No scheduled commands are ready to run.');
            }

            return Command::SUCCESS;
        });
    }

    /**
     * Run in daemon mode for second-based schedules using React PHP Event Loop
     *
     * @return int
     */
    protected function runDaemonMode(): int
    {
        $this->displayInfo('Starting doppar cron daemon...');
        $this->displayInfo('Monitoring for second-based schedules...');
        $this->displayInfo('Press Ctrl+C to stop');
        $this->newLine();

        // Write PID file to track daemon
        $this->writeDaemonPid();

        // Handle SIGTERM and SIGINT for graceful shutdown
        if (function_exists('pcntl_signal')) {
            Loop::addSignal(SIGTERM, function () {
                $this->displayInfo('Received SIGTERM, shutting down gracefully...');
                $this->cleanupDaemonPid();
                Loop::stop();
            });

            Loop::addSignal(SIGINT, function () {
                $this->newLine();
                $this->displayInfo('Received SIGINT, shutting down gracefully...');
                $this->cleanupDaemonPid();
                Loop::stop();
            });
        }

        // Register shutdown handler to clean up
        register_shutdown_function(function () {
            $this->cleanupDaemonPid();
        });

        // Check every second for due commands
        Loop::addPeriodicTimer(1.0, function () {
            try {
                $schedule = new Schedule();
                $schedule->schedule($schedule);

                $allCommands = $schedule->getCommands();

                // Filter only second-based commands
                $secondBasedCommands = array_filter(
                    $allCommands,
                    fn($cmd) => $cmd->isSecondSchedule()
                );

                if (empty($secondBasedCommands)) {
                    // Log once every minute if no commands found
                    static $lastWarning = 0;
                    if (time() - $lastWarning >= 60) {
                        $this->displayWarning('[' . date('H:i:s') . '] No second-based schedules found. Daemon continues monitoring...');
                        $lastWarning = time();
                    }
                    return;
                }

                $currentTimestamp = time();

                // Check and run due commands
                foreach ($secondBasedCommands as $command) {
                    $this->processSecondBasedCommand($command, $currentTimestamp);
                }
            } catch (\Exception $e) {
                $this->displayError('[' . date('H:i:s') . '] Daemon error: ' . $e->getMessage());
                // Continue running even if there's an error
            }
        });

        Loop::run();

        return Command::SUCCESS;
    }

    /**
     * Process a second-based command
     *
     * @param mixed $command
     * @param int $currentTimestamp
     * @return void
     */
    protected function processSecondBasedCommand($command, int $currentTimestamp): void
    {
        $commandKey = $this->getCommandKey($command);

        // Get the interval in seconds from the command
        $interval = $this->getCommandInterval($command);

        if (!$interval) {
            // If we can't determine interval, fall back to isDue() check with throttling
            if ($command->isDue()) {
                if (
                    !isset($this->lastExecution[$commandKey]) ||
                    ($currentTimestamp - $this->lastExecution[$commandKey]) >= 1
                ) {

                    $this->displayInfo('[' . date('H:i:s') . '] Executing: ' . $command->getCommand());
                    $this->executeCommand($command, true);
                    $this->lastExecution[$commandKey] = $currentTimestamp;
                }
            }
            return;
        }

        // Check if it's time to run based on the interval
        if (!isset($this->lastExecution[$commandKey])) {
            // First run
            $this->displayInfo('[' . date('H:i:s') . '] Executing: ' . $command->getCommand() . ' (first run)');
            $this->executeCommand($command, true);
            $this->lastExecution[$commandKey] = $currentTimestamp;
        } else {
            // Check if enough time has passed
            $timeSinceLastExecution = $currentTimestamp - $this->lastExecution[$commandKey];

            if ($timeSinceLastExecution >= $interval) {
                $this->displayInfo('[' . date('H:i:s') . '] Executing: ' . $command->getCommand() . ' (interval: ' . $interval . 's)');
                $this->executeCommand($command, true);
                $this->lastExecution[$commandKey] = $currentTimestamp;
            }
        }
    }

    /**
     * Get the interval in seconds for a command
     *
     * @param mixed $command
     * @return int|null
     */
    protected function getCommandInterval($command): ?int
    {
        return $command->getSecondInterval();
    }

    /**
     * Get a unique key for a command
     *
     * @param mixed $command
     * @return string
     */
    protected function getCommandKey($command): string
    {
        return md5($command->getCommand());
    }

    /**
     * Execute a command with proper handling
     *
     * @param mixed $command
     * @param bool $isSecondBased
     * @return void
     */
    protected function executeCommand($command, bool $isSecondBased = false): void
    {
        try {
            $env = array_merge(getenv(), [
                'APP_RUNNING_IN_CONSOLE' => true,
                'APP_SCHEDULE_RUNNING' => true,
                'APP_SECOND_SCHEDULE' => $isSecondBased ? 'true' : 'false'
            ]);

            if (!$isSecondBased) {
                $this->line('<comment>Running:</comment> ' . $command->getCommand());
            }

            if ($command->shouldRunInBackground()) {
                $this->runInBackground($command, $env);
            } else {
                $this->runInForeground($command, $env);
            }
        } catch (\Exception $e) {
            $this->displayError('Error executing command: ' . $e->getMessage());
        }
    }

    /**
     * Check if daemon is currently running
     *
     * @return bool
     */
    protected function isDaemonRunning(): bool
    {
        $pidFile = $this->getDaemonPidFile();

        if (!file_exists($pidFile)) {
            return false;
        }

        $data = @json_decode(file_get_contents($pidFile), true);
        $pid = $data['pid'] ?? 0;

        if ($pid <= 0) {
            return false;
        }

        // Check if process is actually running
        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }

        // Fallback for systems without posix_kill
        $output = shell_exec(sprintf("ps -p %d -o pid=", $pid));
        return !empty(trim($output));
    }

    /**
     * Write daemon PID file
     *
     * @return void
     */
    protected function writeDaemonPid(): void
    {
        $pidFile = $this->getDaemonPidFile();
        $dir = dirname($pidFile);

        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = [
            'pid' => getmypid(),
            'started_at' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'os' => PHP_OS
        ];

        file_put_contents($pidFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Clean up daemon PID file
     *
     * @return void
     */
    protected function cleanupDaemonPid(): void
    {
        $pidFile = $this->getDaemonPidFile();

        if (file_exists($pidFile)) {
            unlink($pidFile);
        }
    }

    /**
     * Get the daemon PID file path
     *
     * @return string
     */
    protected function getDaemonPidFile(): string
    {
        return storage_path('schedule/cron_daemon.pid');
    }

    protected function runInBackground($command, $env): void
    {
        $phpBinary = PHP_BINARY;
        $poolScript = 'pool';

        $finishId = uniqid('cron_finish_', true);

        if ($command->withoutOverlapping) {
            $command->lock();
        }

        $logDir = storage_path('schedule');
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/cron_' . md5($command->getCommand()) . '.log';

        $lockFile = $command->getLockFile() . '.pid';
        $lockDir = dirname($lockFile);
        if (!file_exists($lockDir)) {
            mkdir($lockDir, 0755, true);
        }

        $commandString = sprintf(
            '(%s %s %s >> %s 2>&1 ; %s %s cron:finish %s %d $? >> %s 2>&1) & echo $!',
            escapeshellarg($phpBinary),
            escapeshellarg($poolScript),
            escapeshellarg($command->getCommand()),
            escapeshellarg($logFile),
            escapeshellarg($phpBinary),
            escapeshellarg($poolScript),
            escapeshellarg($finishId),
            $command->withoutOverlapping ? 1 : 0,
            escapeshellarg($logFile)
        );

        $pid = (int) shell_exec($commandString);

        if (empty($pid)) {
            throw new \RuntimeException('Failed to start background process');
        }

        $processInfo = [
            'pid' => $pid,
            'finish_id' => $finishId,
            'command' => $command->getCommand(),
            'started_at' => date('Y-m-d H:i:s'),
            'log_file' => $logFile,
            'command_string' => $commandString,
            'os' => PHP_OS
        ];

        $maxRetries = 3;
        $retryDelay = 100000;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $written = file_put_contents(
                    $lockFile,
                    json_encode($processInfo, JSON_PRETTY_PRINT),
                    LOCK_EX
                );

                if ($written !== false) {
                    break;
                }

                if ($attempt < $maxRetries) {
                    usleep($retryDelay);
                }
            } catch (\Exception $e) {
                if ($attempt === $maxRetries) {
                    $this->displayError('Failed to write process info after ' . $maxRetries . ' attempts: ' . $e->getMessage());
                    return;
                }
                usleep($retryDelay);
            }
        }

        file_put_contents(
            $logFile,
            sprintf(
                "[%s] Process started (PID: %d)\nCommand: %s\nProcess Info: %s\n\n",
                date('Y-m-d H:i:s'),
                $pid,
                $command->getCommand(),
                json_encode($processInfo, JSON_PRETTY_PRINT)
            ),
            FILE_APPEND
        );
    }

    protected function runInForeground($command, $env): void
    {
        if ($command->withoutOverlapping) {
            $command->lock();
        }

        $process = new Process(['php', 'pool', $command->getCommand()], base_path(), $env);
        $process->setTimeout(null);
        $process->run();

        if ($process->isSuccessful()) {
            // Only show success for non-second-based
            if (!$command->isSecondSchedule()) {
                $this->info('Success: ' . $command->getCommand());
            }
        } else {
            $this->displayError('Error: ' . $command->getCommand());
            $this->displayError('Output: ' . $process->getErrorOutput());
        }

        if ($command->withoutOverlapping) {
            $command->releaseLock();
        }
    }
}
