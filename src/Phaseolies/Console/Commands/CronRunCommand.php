<?php

namespace Phaseolies\Console\Commands;

use App\Schedule\Schedule;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;

class CronRunCommand extends Command
{
    protected static $defaultName = 'cron:run';

    protected function configure()
    {
        $this
            ->setName('cron:run')
            ->setDescription('Run the scheduled commands');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schedule = new Schedule();
        $schedule->schedule($schedule);

        $commandsToRun = $schedule->dueCommands();

        if (empty($commandsToRun)) {
            $output->writeln('<info>No scheduled commands are ready to run.</info>');
            return Command::SUCCESS;
        }

        foreach ($commandsToRun as $command) {
            $output->writeln('<comment>Running:</comment> ' . $command->getCommand());

            $env = array_merge(getenv(), [
                'APP_RUNNING_IN_CONSOLE' => true,
                'APP_SCHEDULE_RUNNING' => true
            ]);

            if ($command->shouldRunInBackground()) {
                $this->runInBackground($command, $output, $env);
            } else {
                $this->runInForeground($command, $output, $env);
            }
        }

        return Command::SUCCESS;
    }

    protected function runInBackground($command, $output, $env): void
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

        $output->writeln('<comment>Executing command:</comment> ' . $commandString);

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
                    $output->writeln('<error>Failed to write process info after ' . $maxRetries . ' attempts: ' . $e->getMessage() . '</error>');
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

        $output->writeln(sprintf(
            '<info>Background process started (PID: %d, Log: %s, Lock: %s)</info>',
            $pid,
            $logFile,
            $lockFile
        ));
    }

    protected function runInForeground($command, $output, $env): void
    {
        if ($command->withoutOverlapping) {
            $command->lock();
        }

        $process = new Process(['php', 'pool', $command->getCommand()], base_path(), $env);
        $process->setTimeout(null);
        $process->run();

        if ($process->isSuccessful()) {
            $output->writeln('<info>Success:</info> ' . $command->getCommand());
        } else {
            $output->writeln('<error>Error:</error> ' . $command->getCommand());
            $output->writeln('<error>Output:</error> ' . $process->getErrorOutput());
        }

        if ($command->withoutOverlapping) {
            $command->releaseLock();
        }
    }
}
