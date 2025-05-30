<?php

namespace Phaseolies\Console\Schedule;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class SchedulePool
{
    protected static $runningProcesses = [];

    /**
     * Call a command through the pool
     *
     * @param string $command The command to execute
     * @param bool $background Run in background (recommended for web)
     * @return array Process information
     */
    public static function call(string $command, bool $background = true): array
    {
        $commandArray = ['php', 'pool'];
        $commandArray = array_merge($commandArray, explode(' ', $command));

        $process = new Process(
            $commandArray,
            base_path(),
            array_merge($_SERVER, $_ENV, [
                'APP_RUNNING_IN_CONSOLE' => true,
                'APP_SCHEDULE_RUNNING' => true
            ]),
            null,
            null
        );

        $process->setTimeout(null);

        if ($background) {
            $process->setOptions([
                'create_new_console' => true
            ]);

            $process->start();

            $processInfo = [
                'pid' => $process->getPid(),
                'start_time' => time()
            ];

            self::$runningProcesses[] = $processInfo;

            return $processInfo;
        } else {
            try {
                $process->run();

                return [
                    'pid' => $process->getPid(),
                    'command' => $command,
                    'status' => $process->isSuccessful() ? 'success' : 'failed',
                    'output' => $process->getOutput(),
                    'error' => $process->getErrorOutput()
                ];
            } catch (ProcessFailedException $e) {
                return [
                    'pid' => $process->getPid(),
                    'command' => $command,
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
            }
        }
    }

    /**
     * Get running processes
     */
    public static function getRunningProcesses(): array
    {
        return self::$runningProcesses;
    }

    /**
     * Check if a process is running by PID
     */
    public static function isProcessRunning(int $pid): bool
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $output = shell_exec("tasklist /FI \"PID eq $pid\"");
            return strpos($output, ' ' . $pid . ' ') !== false;
        } else {
            return file_exists("/proc/$pid");
        }
    }
}
