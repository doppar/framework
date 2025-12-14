<?php

namespace Phaseolies\Console\Commands\Cron;

use App\Schedule\Schedule;
use Phaseolies\Console\Schedule\Command;
use Symfony\Component\Process\Process;

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
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
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
        return $this->executeWithTiming(function() {
            $schedule = new Schedule();
            $schedule->schedule($schedule);

            $allCommands = $schedule->getCommands();

            // Separate second-based and regular commands
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

            // Handle second-based commands
            if (!empty($secondBasedCommands)) {
                $this->displayInfo('Found ' . count($secondBasedCommands) . ' second-based schedule(s)');

                // Check if daemon is running
                if (!$this->isDaemonRunning()) {
                    $this->displayWarning('Second-based schedules detected but daemon is not running!');
                    $this->displayInfo('Start the daemon with: php pool cron:run --daemon');

                    // Optionally run them once in this execution
                    $secondDueCommands = array_filter($secondBasedCommands, fn($cmd) => $cmd->isDue());
                    foreach ($secondDueCommands as $command) {
                        $this->executeCommand($command);
                    }
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
     * Run in daemon mode for second-based schedules
     *
     * @return int
     */
    protected function runDaemonMode(): int
    {
        $this->displayInfo('Starting daemon mode for second-based schedules...');
        $this->displayInfo('Press Ctrl+C to stop');

        // Write PID file to track daemon
        $this->writeDaemonPid();

        // Register shutdown handler to clean up
        register_shutdown_function(function() {
            $this->cleanupDaemonPid();
        });

        // Handle SIGTERM and SIGINT for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, function() {
                $this->displayInfo('Received SIGTERM, shutting down gracefully...');
                $this->cleanupDaemonPid();
                exit(0);
            });

            pcntl_signal(SIGINT, function() {
                $this->displayInfo('Received SIGINT, shutting down gracefully...');
                $this->cleanupDaemonPid();
                exit(0);
            });
        }

        while (true) {
            try {
                // Handle signals if available
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                $schedule = new Schedule();
                $schedule->schedule($schedule);

                $allCommands = $schedule->getCommands();

                // Filter only second-based commands
                $secondBasedCommands = array_filter(
                    $allCommands,
                    fn($cmd) => $cmd->isSecondSchedule()
                );

                if (empty($secondBasedCommands)) {
                    $this->displayWarning('No second-based schedules found. Daemon will continue monitoring...');
                    sleep(10);
                    continue;
                }

                // Check and run due commands
                foreach ($secondBasedCommands as $command) {
                    if ($command->isDue()) {
                        $this->executeCommand($command, true);
                    }
                }

                // Sleep for 100ms to balance between responsiveness and CPU usage
                usleep(100000);

            } catch (\Exception $e) {
                $this->displayError('Daemon error: ' . $e->getMessage());
                $this->displayError('Trace: ' . $e->getTraceAsString());

                // Continue running even if there's an error
                sleep(1);
            }
        }

        return Command::SUCCESS;
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
                $this->displayInfo('Success: ' . $command->getCommand());
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