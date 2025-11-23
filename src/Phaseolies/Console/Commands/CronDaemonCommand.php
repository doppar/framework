<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;

class CronDaemonCommand extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'cron:daemon {action : start, stop, restart, or status}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Manage the cron daemon for second-based schedules';

    /**
     * Execute the console command.
     * Example: php doppar cron:daemon start|stop|restart|status
     *
     * @return int
     */
    protected function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'start' => $this->startDaemon(),
            'stop' => $this->stopDaemon(),
            'restart' => $this->restartDaemon(),
            'status' => $this->showStatus(),
            default => $this->invalidAction($action)
        };
    }

    /**
     * Start the daemon
     *
     * @return int
     */
    protected function startDaemon(): int
    {
        if ($this->isDaemonRunning()) {
            $this->displayWarning('Daemon is already running!');
            return Command::FAILURE;
        }

        $this->displayInfo('Starting cron daemon...');

        $phpBinary = PHP_BINARY;
        $poolScript = base_path('pool');
        $logFile = storage_path('schedule/daemon.log');

        $logDir = dirname($logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Start daemon in background
        $command = sprintf(
            '%s %s cron:run --daemon >> %s 2>&1 & echo $!',
            escapeshellarg($phpBinary),
            escapeshellarg($poolScript),
            escapeshellarg($logFile)
        );

        $pid = (int) shell_exec($command);

        if ($pid > 0) {
            // Wait a moment and verify it's running
            // 0.5 seconds
            usleep(500000); 

            if ($this->isProcessRunning($pid)) {
                $this->displaySuccess(sprintf(
                    'Daemon started successfully (PID: %d)',
                    $pid
                ));
                $this->displayInfo("Log file: {$logFile}");
                return Command::SUCCESS;
            } else {
                $this->displayError('Daemon started but stopped immediately. Check logs.');
                return Command::FAILURE;
            }
        }

        $this->displayError('Failed to start daemon');

        return Command::FAILURE;
    }

    /**
     * Stop the daemon
     *
     * @return int
     */
    protected function stopDaemon(): int
    {
        if (!$this->isDaemonRunning()) {
            $this->displayWarning('Daemon is not running!');
            return Command::FAILURE;
        }

        $pidFile = $this->getDaemonPidFile();
        $data = @json_decode(file_get_contents($pidFile), true);
        $pid = $data['pid'] ?? 0;

        $this->displayInfo("Stopping daemon (PID: {$pid})...");

        // Try graceful shutdown first
        if (function_exists('posix_kill')) {
            posix_kill($pid, SIGTERM);
        } else {
            // Fallback for systems without posix_kill
            exec("kill {$pid}");
        }

        // Wait up to 5 seconds for graceful shutdown
        $waited = 0;
        while ($waited < 5 && $this->isProcessRunning($pid)) {
            // 0.5 seconds
            usleep(500000);
            $waited += 0.5;
        }

        // Force kill if still running
        if ($this->isProcessRunning($pid)) {
            $this->displayWarning('Daemon did not stop gracefully, forcing...');

            if (function_exists('posix_kill')) {
                posix_kill($pid, SIGKILL);
            } else {
                exec("kill -9 {$pid}");
            }

            usleep(500000);
        }

        // Verify stopped and clean up
        if (!$this->isProcessRunning($pid)) {
            if (file_exists($pidFile)) {
                unlink($pidFile);
            }
            $this->displaySuccess('Daemon stopped successfully');
            return Command::SUCCESS;
        }

        $this->displayError('Failed to stop daemon');

        return Command::FAILURE;
    }

    /**
     * Restart the daemon
     *
     * @return int
     */
    protected function restartDaemon(): int
    {
        $this->displayInfo('Restarting daemon...');

        if ($this->isDaemonRunning()) {
            $stopResult = $this->stopDaemon();
            if ($stopResult !== Command::SUCCESS) {
                return $stopResult;
            }

            // Wait a moment before starting
            sleep(1);
        }

        return $this->startDaemon();
    }

    /**
     * Show daemon status
     *
     * @return int
     */
    protected function showStatus(): int
    {
        $pidFile = $this->getDaemonPidFile();

        if (!$this->isDaemonRunning()) {
            $this->displayWarning('Daemon is NOT running');

            if (file_exists($pidFile)) {
                $this->displayInfo('Stale PID file exists, cleaning up...');
                unlink($pidFile);
            }

            return Command::SUCCESS;
        }

        $data = @json_decode(file_get_contents($pidFile), true);

        $this->displaySuccess('Daemon is RUNNING');
        $this->line('');
        $this->line('<info>Process Information:</info>');
        $this->line("  PID: {$data['pid']}");
        $this->line("  Started: {$data['started_at']}");
        $this->line("  PHP Version: {$data['php_version']}");
        $this->line("  OS: {$data['os']}");

        $startTime = strtotime($data['started_at']);
        $uptime = time() - $startTime;
        $this->line("  Uptime: " . $this->formatUptime($uptime));

        $logFile = storage_path('schedule/daemon.log');
        if (file_exists($logFile)) {
            $this->line("  Log: {$logFile}");
            $this->line("  Log Size: " . $this->formatBytes(filesize($logFile)));
        }

        return Command::SUCCESS;
    }

    /**
     * Handle invalid action
     *
     * @param string $action
     * @return int
     */
    protected function invalidAction(string $action): int
    {
        $this->displayError("Invalid action: {$action}");
        $this->line('');
        $this->line('Available actions:');
        $this->line('  <comment>start</comment>   - Start the daemon');
        $this->line('  <comment>stop</comment>    - Stop the daemon');
        $this->line('  <comment>restart</comment> - Restart the daemon');
        $this->line('  <comment>status</comment>  - Show daemon status');

        return Command::FAILURE;
    }

    /**
     * Check if daemon is running
     *
     * @return bool
     */
    protected function isDaemonRunning(): bool
    {
        $pidFile = $this->getDaemonPidFile();

        if (!file_exists($pidFile)) {
            return false;
        }

        $data = @json_decode(file_get_contents($pidFile), true);
        $pid = $data['pid'] ?? 0;

        return $this->isProcessRunning($pid);
    }

    /**
     * Check if a process is running
     *
     * @param int $pid
     * @return bool
     */
    protected function isProcessRunning(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }

        // Fallback
        $output = shell_exec(sprintf("ps -p %d -o pid=", $pid));
        return !empty(trim($output));
    }

    /**
     * Get daemon PID file path
     *
     * @return string
     */
    protected function getDaemonPidFile(): string
    {
        return storage_path('schedule/cron_daemon.pid');
    }

    /**
     * Format uptime in human readable format
     *
     * @param int $seconds
     * @return string
     */
    protected function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}m";
        if ($secs > 0 || empty($parts)) $parts[] = "{$secs}s";

        return implode(' ', $parts);
    }

    /**
     * Format bytes in human readable format
     *
     * @param int $bytes
     * @return string
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
