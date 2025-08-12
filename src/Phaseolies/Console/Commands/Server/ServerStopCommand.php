<?php

namespace Phaseolies\Console\Commands\Server;

use Symfony\Component\Process\Process;
use Phaseolies\Console\Schedule\Command;

class ServerStopCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'server:stop {port?}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Stop the development server';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return $this->executeWithTiming(function() {
            $port = $this->argument('port');
            if ($port) {
                if (!self::isPortInUse($port)) {
                    $this->displayError("Port $port is not in use");
                    return 1;
                }
                self::stopServer($port);
                $this->displaySuccess("Server stopped on <fg=green>http://localhost:$port</>");
                return 0;
            }

            self::stopAllServers();
            $this->displaySuccess("All servers stopped");
            return 0;
        });
    }

    private function isPortOk(?int $port): bool
    {
        if (empty($port)) {
            return false;
        }

        if (!is_int($port)) {
            $this->displayError('Port must be an integer');
            return false;
        }

        return true;
    }

    private function isPortInUse(int $port): bool
    {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);

        if ($socket) {
            fclose($socket);
            return true;
        }

        return false;
    }

    private function stopAllServers(): void
    {
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            // Windows
            $command = "taskkill /F /IM php.exe";
        } else {
            // Linux / macOS
            $command = "pkill -f 'php -S localhost:*'";
        }

        $process = Process::fromShellCommandline($command);
        $process->run();
    }

    private function stopServer(int $port): void
    {
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            // Windows
            $command = "taskkill /F /IM php.exe";
        } else {
            // Linux / macOS
            $command = "pkill -f 'php -S localhost:$port'";
        }

        $process = Process::fromShellCommandline($command);
        $process->run();
    }
}
