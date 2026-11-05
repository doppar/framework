<?php

namespace Phaseolies\Console\Commands\Cron;

use Phaseolies\Console\Schedule\Command;
use Phaseolies\Console\Schedule\SchedulePool;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;
use React\EventLoop\Loop;

class CronRunCommand extends Command
{
    /**
     * The application class that registers the scheduled commands
     *
     * @var string
     */
    protected const SCHEDULE_CLASS = 'App\\Schedule\\Schedule';

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
     * Number of scheduled commands that failed to start or exited with an error
     *
     * @var int
     */
    protected int $failedCommands = 0;

    /**
     * Handle of the lock file the daemon holds for as long as it runs
     *
     * @var resource|null
     */
    protected $daemonLock = null;

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
            $schedule = $this->makeSchedule();

            if ($schedule === null) {
                return Command::FAILURE;
            }

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

            // A task that could not run must not look like a clean run to cron
            // monitors, or to anyone reading the exit status.
            if ($this->failedCommands > 0) {
                $this->displayError($this->failedCommands . ' scheduled command(s) failed.');

                return Command::FAILURE;
            }

            return Command::SUCCESS;
        });
    }

    /**
     * Build the application's schedule
     *
     * @return object|null
     */
    protected function makeSchedule(): ?object
    {
        $class = self::SCHEDULE_CLASS;

        if (!class_exists($class)) {
            $this->displayError("The schedule class {$class} was not found. Create it, or remove cron:run from your crontab.");

            return null;
        }

        $schedule = new $class();
        $schedule->schedule($schedule);

        return $schedule;
    }

    /**
     * Run in daemon mode for second-based schedules using React PHP Event Loop
     *
     * @return int
     */
    protected function runDaemonMode(): int
    {
        // Only one daemon may run. The lock is held for the daemon's whole life
        // and is released by the OS if it dies, so it can never go stale, and
        // two starts at the same instant cannot both win. This makes it safe to
        // keep `cron:run --daemon` in the crontab.
        if (!$this->acquireDaemonLock()) {
            $this->displayInfo('The cron daemon is already running; nothing to do.');

            return Command::SUCCESS;
        }

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
                $schedule = $this->makeSchedule();

                if ($schedule === null) {
                    return;
                }

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
            if ($command->isDue(false)) {
                // First run
                $this->displayInfo('[' . date('H:i:s') . '] Executing: ' . $command->getCommand() . ' (first run)');
                $this->executeCommand($command, true);
                $this->lastExecution[$commandKey] = $currentTimestamp;
            }
        } else {
            // Check if enough time has passed
            $timeSinceLastExecution = $currentTimestamp - $this->lastExecution[$commandKey];

            if ($timeSinceLastExecution >= $interval && $command->isDue(false)) {
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
            $env = SchedulePool::buildEnv([
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
        } catch (\Throwable $e) {
            $this->failedCommands++;
            $this->displayError('Error executing command: ' . $e->getMessage());
        }
    }

    /**
     * Take the exclusive daemon lock without waiting
     *
     * @return bool
     */
    protected function acquireDaemonLock(): bool
    {
        $file = dirname($this->getDaemonPidFile()) . '/cron_daemon.lock';
        $dir = dirname($file);

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $handle = @fopen($file, 'c');

        if ($handle === false) {
            // Storage is not writable; the PID file check below is the best we can do.
            return !$this->isDaemonRunning();
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }

        $this->daemonLock = $handle;

        return true;
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

        $data = @json_decode((string) @file_get_contents($pidFile), true);
        $pid = (int) ($data['pid'] ?? 0);

        return SchedulePool::isProcessRunning($pid);
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
            @unlink($pidFile);
        }

        if ($this->daemonLock !== null) {
            flock($this->daemonLock, LOCK_UN);
            fclose($this->daemonLock);
            $this->daemonLock = null;
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

    /**
     * Build the shell command that runs a task detached from this process
     *
     * @param string $command
     * @param string $logFile
     * @param string $finishId
     * @param bool $releaseLock
     * @param array<string, string> $env
     * @return string
     */
    protected function buildBackgroundCommand(
        string $command,
        string $logFile,
        string $finishId,
        bool $releaseLock,
        array $env = []
    ): string {
        $php = escapeshellarg(SchedulePool::phpBinary());
        $pool = escapeshellarg(SchedulePool::poolScript());
        $log = escapeshellarg($logFile);
        $arguments = implode(' ', array_map('escapeshellarg', SchedulePool::splitCommand($command)));

        $assignments = '';

        foreach ($env as $name => $value) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $name)) {
                $assignments .= $name . '=' . escapeshellarg((string) $value) . ' ';
            }
        }

        return sprintf(
            '(cd %s && %s%s %s %s >> %s 2>&1 ; %s %s cron:finish %s %d $? >> %s 2>&1) > /dev/null 2>&1 < /dev/null & echo $!',
            escapeshellarg(base_path()),
            $assignments,
            $php,
            $pool,
            $arguments,
            $log,
            $php,
            $pool,
            escapeshellarg($finishId),
            $releaseLock ? 1 : 0,
            $log
        );
    }

    /**
     * Start a shell command that detaches itself, and return its PID
     *
     * @param string $shellCommand
     * @return int|null
     */
    protected function startDetached(string $shellCommand): ?int
    {
        return SchedulePool::startDetached($shellCommand);
    }

    /**
     * Start a command in the background and return immediately
     *
     * @param mixed $command
     * @param array $env
     * @return void
     */
    protected function runInBackground($command, $env): void
    {
        if (SchedulePool::isWindows()) {
            $this->displayWarning('Background execution is not supported on Windows; running in the foreground: ' . $command->getCommand());
            $this->runInForeground($command, $env);

            return;
        }

        $finishId = uniqid('cron_finish_', true);

        if ($command->withoutOverlapping) {
            $command->lock();
        }

        if ($command->getOutputTo() !== null) {
            $logFile = $command->getOutputTo();
        } else {
            $logDir = storage_path('schedule');
            if (!file_exists($logDir)) {
                mkdir($logDir, 0755, true);
            }
            $logFile = $logDir . '/cron_' . md5($command->getCommand()) . '.log';
        }

        $lockFile = $command->getLockFile() . '.pid';
        $lockDir = dirname($lockFile);
        if (!file_exists($lockDir)) {
            mkdir($lockDir, 0755, true);
        }

        $flags = array_intersect_key($env, array_flip([
            'APP_RUNNING_IN_CONSOLE',
            'APP_SCHEDULE_RUNNING',
            'APP_SECOND_SCHEDULE',
        ]));

        $commandString = $this->buildBackgroundCommand(
            $command->getCommand(),
            $logFile,
            $finishId,
            (bool) $command->withoutOverlapping,
            $flags
        );

        $pid = $this->startDetached($commandString);

        if ($pid === null) {
            // shell_exec, exec and proc_open are all unavailable (or the shell
            // returned no PID). Do not lose the task: run it here instead.
            $this->displayWarning('Could not start a background process; running in the foreground: ' . $command->getCommand());

            if ($command->withoutOverlapping) {
                $command->releaseLock();
            }

            $this->runInForeground($command, $env);

            return;
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

        // Best effort: the log path can be unwritable, and that must not turn a
        // started job into a failed run.
        @file_put_contents(
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

    /**
     * Run a command to completion and record whether it succeeded
     *
     * @param mixed $command
     * @param array $env
     * @return void
     */
    protected function runInForeground($command, $env): void
    {
        if ($command->withoutOverlapping) {
            $command->lock();
        }

        try {
            $result = $this->runToCompletion($command->getCommand(), $env);

            if ($result['code'] === 0) {
                // Only show success for non-second-based
                if (!$command->isSecondSchedule()) {
                    $this->info('Success: ' . $command->getCommand());
                }
            } else {
                $this->failedCommands++;
                $this->displayError('Error: ' . $command->getCommand() . ' (exit code ' . $result['code'] . ')');
                $this->displayError('Output: ' . ($result['stderr'] !== '' ? $result['stderr'] : $result['stdout']));
            }
        } finally {
            if ($command->withoutOverlapping) {
                $command->releaseLock();
            }
        }
    }

    /**
     * Run a pool command and wait for it.
     *
     * @param string $command
     * @param array $env
     * @return array{code: int, stdout: string, stderr: string}
     */
    protected function runToCompletion(string $command, array $env): array
    {
        if (SchedulePool::isFunctionEnabled('proc_open')) {
            $process = new Process(SchedulePool::buildProcessArguments($command), base_path(), $env);
            $process->setTimeout(null);
            $process->run();

            return [
                'code' => (int) $process->getExitCode(),
                'stdout' => $process->getOutput(),
                'stderr' => $process->getErrorOutput(),
            ];
        }

        if (SchedulePool::isFunctionEnabled('exec')) {
            $arguments = implode(' ', array_map('escapeshellarg', SchedulePool::buildProcessArguments($command)));
            $lines = [];
            $code = 0;

            exec(sprintf('cd %s && %s 2>&1', escapeshellarg(base_path()), $arguments), $lines, $code);

            return ['code' => $code, 'stdout' => implode("\n", $lines), 'stderr' => ''];
        }

        return $this->runInProcess($command);
    }

    /**
     * Run a pool command through the console application in this process
     *
     * @param string $command
     * @return array{code: int, stdout: string, stderr: string}
     */
    protected function runInProcess(string $command): array
    {
        $application = $this->getApplication();
        $tokens = SchedulePool::splitCommand($command);

        if ($application === null || $tokens === []) {
            throw new \RuntimeException(
                'Cannot run scheduled commands: proc_open, exec and shell_exec are disabled on this host.'
            );
        }

        $output = new BufferedOutput();
        $code = $application->find($tokens[0])->run(new ArgvInput(array_merge(['pool'], $tokens)), $output);

        return ['code' => $code, 'stdout' => $output->fetch(), 'stderr' => ''];
    }
}
