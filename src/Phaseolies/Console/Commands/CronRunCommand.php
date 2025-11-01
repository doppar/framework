<?php

namespace Phaseolies\Console\Commands;

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
    protected $name = 'cron:run';

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
        return $this->executeWithTiming(function() {
            $schedule = new Schedule();
            $schedule->schedule($schedule);

            $commandsToRun = $schedule->dueCommands();

            if (empty($commandsToRun)) {
                $this->displayInfo('No scheduled commands are ready to run.');
                return Command::SUCCESS;
            }

            foreach ($commandsToRun as $command) {
                $this->line('<comment>Running:</comment> ' . $command->getCommand());

                $env = array_merge(getenv(), [
                    'APP_RUNNING_IN_CONSOLE' => true,
                    'APP_SCHEDULE_RUNNING' => true
                ]);

                if ($command->shouldRunInBackground()) {
                    $this->runInBackground($command, $env);
                } else {
                    $this->runInForeground($command, $env);
                }
            }

            $this->displaySuccess('Executed ' . count($commandsToRun) . ' scheduled commands');
            return Command::SUCCESS;
        });
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

        $this->line('<comment>Executing command:</comment> ' . $commandString);

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

        $this->displayInfo(sprintf(
            'Background process started (PID: %d, Log: %s, Lock: %s)',
            $pid,
            $logFile,
            $lockFile
        ));
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
            $this->displayInfo('Success: ' . $command->getCommand());
        } else {
            $this->displayError('Error: ' . $command->getCommand());
            $this->displayError('Output: ' . $process->getErrorOutput());
        }

        if ($command->withoutOverlapping) {
            $command->releaseLock();
        }
    }
}
