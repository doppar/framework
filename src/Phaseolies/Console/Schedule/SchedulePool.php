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
     * Build a subprocess environment from the current $_SERVER/$_ENV state
     *
     * @param array $overrides
     * @return array<string, string>
     */
    public static function buildEnv(array $overrides = []): array
    {
        $env = [];

        foreach (array_merge($_SERVER, $_ENV, $overrides) as $key => $value) {
            // $_SERVER carries non-scalar entries (e.g. 'argv') that have
            // no meaningful string form as an env var — skip them.
            if ($value === null || !is_scalar($value)) {
                continue;
            }

            $env[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return $env;
    }

    /**
     * Call a command through the pool
     *
     * @param string $command
     * @param bool $background
     * @return array
     */
    public static function call(string $command, bool $background = false): array
    {
        $commandArray = self::buildProcessArguments($command);

        $process = new Process(
            $commandArray,
            base_path(),
            self::buildEnv([
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
     * @param int $pid
     * @return bool
     */
    public static function isProcessRunning(int $pid): bool
    {
        // A corrupt or hand-edited lock file can hold any number; posix_kill()
        // throws for values outside the range of a PID.
        if ($pid <= 0 || $pid > 2147483647) {
            return false;
        }

        if (self::isWindows()) {
            if (!self::isFunctionEnabled('shell_exec')) {
                return false;
            }

            $output = (string) shell_exec("tasklist /FI \"PID eq $pid\"");

            return strpos($output, ' ' . $pid . ' ') !== false;
        }

        if (function_exists('posix_kill')) {
            try {
                if (@posix_kill($pid, 0)) {
                    return true;
                }
            } catch (\ValueError) {
                return false;
            }

            // EPERM (1): the process exists but belongs to another user.
            return function_exists('posix_get_last_error') && posix_get_last_error() === 1;
        }

        if (is_dir('/proc/self')) {
            return file_exists("/proc/$pid");
        }

        if (self::isFunctionEnabled('shell_exec')) {
            return trim((string) shell_exec(sprintf('ps -p %d -o pid= 2>/dev/null', $pid))) !== '';
        }

        return false;
    }

    /**
     * Determine if the current platform is Windows
     *
     * @return bool
     */
    public static function isWindows(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * Determine if a PHP function can be called (it exists and is not disabled)
     *
     * @param string $function
     * @return bool
     */
    public static function isFunctionEnabled(string $function): bool
    {
        return function_exists($function);
    }

    /**
     * Get the PHP binary that child commands must run with
     *
     * @return string
     */
    public static function phpBinary(): string
    {
        $binary = PHP_BINARY;

        // Under FPM/CGI/LiteSpeed PHP_BINARY is not a command line binary.
        if (is_executable($binary) && !preg_match('/(fpm|cgi|lsphp)/i', basename($binary))) {
            return $binary;
        }

        $sibling = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php';

        return is_executable($sibling) ? $sibling : 'php';
    }

    /**
     * Get the absolute path of the application's pool script
     *
     * @return string
     */
    public static function poolScript(): string
    {
        return base_path('pool');
    }

    /**
     * Build the argument list that runs a pool command in a child process
     *
     * @param string $command
     * @return array<int, string>
     */
    public static function buildProcessArguments(string $command): array
    {
        return array_merge([self::phpBinary(), self::poolScript()], self::splitCommand($command));
    }

    /**
     * Split a scheduled command into arguments the way a shell would
     *
     * @param string $command
     * @return array<int, string>
     */
    public static function splitCommand(string $command): array
    {
        $command = trim($command);
        $tokens = [];
        $current = '';
        $inToken = false;
        $quote = null;
        $length = strlen($command);

        for ($i = 0; $i < $length; $i++) {
            $char = $command[$i];

            if ($quote === "'") {
                if ($char === "'") {
                    $quote = null;
                } else {
                    $current .= $char;
                }

                continue;
            }

            if ($quote === '"') {
                if ($char === '\\' && $i + 1 < $length && strpos('"\\$`', $command[$i + 1]) !== false) {
                    $current .= $command[++$i];
                } elseif ($char === '"') {
                    $quote = null;
                } else {
                    $current .= $char;
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $quote = $char;
                $inToken = true;

                continue;
            }

            if ($char === '\\' && $i + 1 < $length) {
                $current .= $command[++$i];
                $inToken = true;

                continue;
            }

            if (ctype_space($char)) {
                if ($inToken) {
                    $tokens[] = $current;
                    $current = '';
                    $inToken = false;
                }

                continue;
            }

            $current .= $char;
            $inToken = true;
        }

        if ($quote !== null) {
            return preg_split('/\s+/', $command, -1, PREG_SPLIT_NO_EMPTY);
        }

        if ($inToken) {
            $tokens[] = $current;
        }

        return $tokens;
    }

    /**
     * Start a shell command that backgrounds itself and prints its PID
     *
     * @param string $shellCommand
     * @return int|null
     */
    public static function startDetached(string $shellCommand): ?int
    {
        if (self::isFunctionEnabled('shell_exec')) {
            $output = shell_exec($shellCommand);
        } elseif (self::isFunctionEnabled('exec')) {
            $lines = [];
            exec($shellCommand, $lines);
            $output = implode("\n", $lines);
        } elseif (self::isFunctionEnabled('proc_open')) {
            $process = Process::fromShellCommandline($shellCommand);
            $process->run();
            $output = $process->getOutput();
        } else {
            return null;
        }

        $pid = (int) trim((string) $output);

        return $pid > 0 ? $pid : null;
    }
}
