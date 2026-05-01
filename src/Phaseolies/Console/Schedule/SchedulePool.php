<?php

namespace Phaseolies\Console\Schedule;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class SchedulePool
{
    /**
     * List of processes currently managed by the schedule pool.
     *
     * @var array<int, array{pid:int, start_time:int}>
     */
    protected static $runningProcesses = [];

    /**
     * Call a command through the pool
     *
     * @param string $command
     * @param bool $background
     * @return array
     */
    public static function call(string $command, bool $background = false): array
    {
        $commandArray = array_merge(
            ['php', 'pool'],
            preg_split('/\s+/', trim($command))
        );

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
            $pid = null;

            try {
                $process->start();
                $pid = $process->getPid();
                $process->wait();

                return [
                    'pid' => $pid,
                    'command' => $command,
                    'status' => $process->isSuccessful() ? 'success' : 'failed',
                    'output' => $process->getOutput(),
                    'error' => $process->getErrorOutput()
                ];
            } catch (ProcessFailedException $e) {
                return [
                    'pid' => $pid,
                    'command' => $command,
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
            }
        }
    }

    /**
     * Get running processes
     *
     * @return array
     */
    public static function getRunningProcesses(): array
    {
        return self::$runningProcesses;
    }

    /**
     * Check if a process is running by PID
     *
     * @return bool
     */
    public static function isProcessRunning(int $pid): bool
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $output = shell_exec("tasklist /FI \"PID eq $pid\"");

            return strpos($output, ' ' . $pid . ' ') !== false;
        }

        return file_exists("/proc/$pid");
    }
}
