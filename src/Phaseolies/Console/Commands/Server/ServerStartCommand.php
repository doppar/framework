<?php

namespace Phaseolies\Console\Commands\Server;

use Symfony\Component\Process\Process;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Phaseolies\Application;
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
     * Time each open connection was accepted (or last served), keyed by client address.
     *
     * @var array<string, float>
     */
    private array $connections = [];

    /**
     * Partial output line waiting for its newline.
     */
    private string $pendingOutput = '';

    /**
     * Clients whose current request was already reported by the request logger.
     *
     * @var array<string, bool>
     */
    private array $reported = [];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        return $this->executeWithTiming(function () {
            $port = $this->argument('port');

            $background = $this->option('background') || $this->option('bg');

            if (!self::isPortOk($port)) {
                $port = self::DEFAULT_PORT;
            }

            $port = self::determineAvailablePort($port);

            if (!$background) {
                $this->displayBanner();
            }

            $this->displaySuccess("Server started on <fg=green>http://localhost:$port</>");

            if (!$background) {
                $this->line('  <fg=gray>Press Ctrl+C to stop the server</>');
                $this->newLine();
            }

            self::startServer($port, $background);

            return Command::SUCCESS;
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
            '-d',
            'auto_prepend_file=' . __DIR__ . '/request_logger.stub',
            '-S',
            "localhost:$port",
            '-t',
            'public',
            'server.php'
        ]);
        $process->setTimeout(null);
        $process->start();

        $process->wait(function ($type, $buffer) {
            $this->handleServerOutput($buffer);
        });

        $this->handleServerOutput("\n");
    }

    /**
     * Print the Doppar logo with the framework version.
     *
     * @return void
     */
    private function displayBanner(): void
    {
        $logo = [
            '    ____                           ',
            '   / __ \\____  ____  ____  ____ ______',
            '  / / / / __ \\/ __ \\/ __ \\/ __ `/ ___/',
            ' / /_/ / /_/ / /_/ / /_/ / /_/ / /    ',
            '/_____/\\____/ .___/ .___/\\__,_/_/     ',
            '           /_/   /_/                  ',
        ];

        foreach ($logo as $row) {
            $this->line('<fg=cyan>' . OutputFormatter::escape($row) . '</>');
        }

        $this->line(str_repeat(' ', 26) . '<fg=red>v' . Application::VERSION . '</>');
        $this->line('<fg=gray>' . str_repeat('-', 48) . '</>');
        $this->newLine();
    }

    /**
     * Split raw server output into lines and render each one.
     *
     * @param string $buffer
     * @return void
     */
    private function handleServerOutput(string $buffer): void
    {
        $this->pendingOutput .= $buffer;

        while (($pos = strpos($this->pendingOutput, "\n")) !== false) {
            $line = trim(substr($this->pendingOutput, 0, $pos));
            $this->pendingOutput = substr($this->pendingOutput, $pos + 1);

            if ($line !== '') {
                $this->renderServerLine($line);
            }
        }
    }

    /**
     * Render a single PHP built-in server log line.
     *
     * @param string $line
     * @return void
     */
    private function renderServerLine(string $line): void
    {
        $now = microtime(true);

        if (str_starts_with($line, "DOPPAR_REQUEST\t")) {
            [, $client, $status, $method, $uri, $ms] = explode("\t", $line) + array_fill(0, 6, '');
            $this->reported[$client] = true;
            $this->printRequest((int) $status, $method, $uri, (float) $ms);
            return;
        }

        if (!preg_match('/^\[[^\]]+\]\s+(\S+)\s+(Accepted|Closing|\[(\d{3})\]:\s+(\S+)\s+(.*))$/', $line, $m)) {
            $this->line('  <fg=gray>' . OutputFormatter::escape($line) . '</>');
            return;
        }

        [, $client, $event] = $m;

        if ($event === 'Accepted') {
            $this->connections[$client] = $now;
            return;
        }

        if ($event === 'Closing') {
            unset($this->connections[$client], $this->reported[$client]);
            return;
        }

        if (isset($this->reported[$client])) {
            return;
        }

        $status = (int) $m[3];
        $method = $m[4];
        $uri = $m[5];
        $elapsed = $now - ($this->connections[$client] ?? $now);
        $this->connections[$client] = $now;

        $this->printRequest($status, $method, $uri, $elapsed * 1000);
    }

    /**
     * Print one request line: status and path on the left, duration on the right.
     *
     * @param int $status
     * @param string $method
     * @param string $uri
     * @param float $milliseconds
     * @return void
     */
    private function printRequest(int $status, string $method, string $uri, float $milliseconds): void
    {
        $color = match (true) {
            $status >= 500 => 'red',
            $status >= 400 => 'yellow',
            $status >= 300 => 'cyan',
            default => 'green',
        };

        $duration = $milliseconds >= 1000
            ? sprintf('%.2fs', $milliseconds / 1000)
            : sprintf('%.2fms', $milliseconds);

        $left = sprintf('%s %s', $method, $uri);
        $plainLeft = sprintf('  %s %s', $status, $left);
        $dots = max(1, (new Terminal())->getWidth() - mb_strlen($plainLeft) - mb_strlen($duration) - 4);

        $this->line(sprintf(
            '  <fg=%s>%s</> <fg=white>%s</> <fg=gray>%s</> <fg=%s>%s</>',
            $color,
            $status,
            OutputFormatter::escape($left),
            str_repeat('.', $dots),
            $color,
            $duration
        ));
    }
}
