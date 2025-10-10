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
            if (!self::isPortOk($port)) {
                return 1;
            }
            if ($port) {
                if (!self::isPortInUse($port)) {
                    $this->displayError("Port $port is not in use");
                    return 1;
                }

                $stopped = self::stopServer($port);
                if (count($stopped) === 0) {
                    $this->displayError("No PHP server found on port $port");
                    return 1;
                }

                self::displayStopped($stopped);
                return 0;
            }

            // Stop all servers
            $stopped = self::stopAllServers();
            if (count($stopped) === 0) {
                $this->displayError('No background PHP servers were stopped');
                return 1;
            }

            self::displayStopped($stopped);
            $this->displaySuccess('All matching servers stopped');
            return 0;
        });
    }

    /**
     * Stop all PHP built-in servers and return a list of stopped items [pid, port].
     *
     * @param array<int,array{pid:int,port:int}> $stopped
     */
    private function displayStopped(array $stopped): void
    {
        foreach ($stopped as $item) {
            $this->line("<fg=yellow>Stopped:</> <fg=green>http://localhost:" . $item['port'] . "</> (PID " . $item['pid'] . ")");
        }
    }

    private function isPortOk(mixed $port): bool
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

    /**
     * Stop all PHP built-in servers and return a list of stopped items [pid, port].
     *
     * @return array<int,array{pid:int,port:int}>
     */
    private function stopAllServers(): array
    {
        $servers = self::listPhpServers();
        if (empty($servers)) {
            return [];
        }

        self::killServers($servers);
        return $servers;
    }

    /**
     * Stop PHP built-in servers for a specific port and return a list of stopped items [pid, port].
     *
     * @return array<int,array{pid:int,port:int}>
     */
    private function stopServer(int $port): array
    {
        $servers = self::listPhpServers($port);
        if (empty($servers)) {
            return [];
        }

        self::killServers($servers);
        return $servers;
    }

    /**
     * List PHP built-in servers (by PID and port). If $port is provided, filter by that port.
     *
     * @param int|null $port
     * @return array<int,array{pid:int,port:int}>
     */
    private function listPhpServers(?int $port = null): array
    {
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            // Windows: use netstat to map local ports to PIDs, then filter PIDs by php.exe
            $cmd = 'netstat -ano -p tcp | findstr LISTENING';
            $process = Process::fromShellCommandline($cmd);
            $process->run();
            $output = $process->getOutput();
            $servers = [];

            foreach (preg_split("/(\r\n|\n|\r)/", $output) as $line) {
                if (trim($line) === '') { continue; }
                // Example line:  TCP    127.0.0.1:8000     0.0.0.0:0      LISTENING       1234
                if (!preg_match('/\s+([0-9\.]+):(\d+)\s+[^\s]+\s+LISTENING\s+(\d+)/', $line, $m)) {
                    continue;
                }
                $ip = $m[1];
                $p = (int)$m[2];
                $pid = (int)$m[3];
                if ($ip !== '127.0.0.1') { continue; }
                if ($port !== null && $p !== $port) { continue; }

                // Ensure the process is php.exe
                $check = Process::fromShellCommandline('tasklist /FI "IMAGENAME eq php.exe" /FI "PID eq ' . $pid . '"');
                $check->run();
                if (stripos($check->getOutput(), 'php.exe') === false) { continue; }

                $servers[] = ['pid' => $pid, 'port' => $p];
            }

            return $servers;
        }

        // Linux / macOS: use pgrep to find php -S localhost:<port>
        $pattern = $port === null ? "php -S localhost:" : "php -S localhost:$port";
        $process = Process::fromShellCommandline("pgrep -fa '" . $pattern . "'");
        $process->run();
        $output = $process->getOutput();
        $servers = [];
        foreach (preg_split("/(\r\n|\n|\r)/", $output) as $line) {
            if (trim($line) === '') { continue; }
            // Line format: "12345 php -S localhost:8000 -t public"
            if (!preg_match('/^(\d+)\s+.*php\s+-S\s+localhost:(\d+)/', $line, $m)) {
                continue;
            }
            $servers[] = ['pid' => (int)$m[1], 'port' => (int)$m[2]];
        }
        return $servers;
    }

    /**
     * Kill the provided servers by PID.
     *
     * @param array<int,array{pid:int,port:int}> $servers
     */
    private function killServers(array $servers): void
    {
        if (empty($servers)) { return; }

        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            foreach ($servers as $s) {
                $proc = Process::fromShellCommandline('taskkill /F /PID ' . (int)$s['pid']);
                $proc->run();
            }
            return;
        }

        // Linux / macOS
        $pids = array_map(fn($s) => (int)$s['pid'], $servers);
        $cmd = 'kill -9 ' . implode(' ', $pids);
        $proc = Process::fromShellCommandline($cmd);
        $proc->run();
    }
}
