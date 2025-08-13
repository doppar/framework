<?php

namespace Phaseolies\Console\Commands\Server;

use Symfony\Component\Process\Process;
use Phaseolies\Console\Schedule\Command;

class ServerStartCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'server:start {port?} {--background} {--bg}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Start the development server';

    protected const DEFAULT_PORT = 8000;

    protected const MAX_PORT_ATTEMPTS = 10;

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return $this->executeWithTiming(function () {
            $port = $this->argument('port');

            $background = $this->option('background') || $this->option('bg');

            if (!self::isPortOk($port)) {
                $port = self::DEFAULT_PORT;
            }

            $port = self::determineAvailablePort($port);

            $this->displaySuccess("Server started on <fg=green>http://localhost:$port</>");
            self::startServer($port, $background);

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

    private function determineAvailablePort(int $desiredPort): int
    {
        $attempts = 0;
        $currentPort = $desiredPort;

        while ($attempts < self::MAX_PORT_ATTEMPTS) {
            if (!self::isPortInUse($currentPort)) {
                if ($currentPort !== $desiredPort) {
                    $this->displayInfo("Port $desiredPort is in use. Using port $currentPort instead.");
                }
                return $currentPort;
            }

            $currentPort++;
            $attempts++;
        }

        throw new \RuntimeException(
            "Unable to find an available port after $attempts attempts. " .
                "Please specify a different port or close the conflicting application."
        );
    }

    private function startServer(int $port, bool $background): void
    {
        if ($background) {
            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                // Windows
                $command = "start /B php -S localhost:$port -t public server.php";
            } else {
                // Linux / macOS
                $command = "nohup php -S localhost:$port -t public server.php > /dev/null 2>&1 &";
            }

            $process = Process::fromShellCommandline($command);
            $process->run();
            return;
        }

        // Foreground mode
        $process = new Process([
            'php',
            '-S',
            "localhost:$port",
            '-t',
            'public',
            'server.php'
        ]);
        $process->setTimeout(null);
        $process->start();

        $process->wait(function ($type, $buffer) {
            $this->displayInfo($buffer);
        });
    }
}
