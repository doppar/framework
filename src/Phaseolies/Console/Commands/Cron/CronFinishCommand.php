<?php

namespace Phaseolies\Console\Commands\Cron;

use Phaseolies\Console\Schedule\Command;

class CronFinishCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'cron:finish {finish_id} {release_lock} {exit_code}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Handle completion of scheduled commands';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $finishId = $this->argument('finish_id');
        $shouldReleaseLock = (bool)$this->argument('release_lock');
        $exitCode = (int)$this->argument('exit_code');

        // Find and clean up the process
        $directories = array_unique(array_filter([
            $this->scheduleDirectory(),
            sys_get_temp_dir(),
        ]));

        foreach ($directories as $directory) {
            foreach (glob($directory . '/doppar_cron_lock_*.pid') ?: [] as $pidFile) {
                $processInfo = json_decode((string) @file_get_contents($pidFile), true);

                if (!is_array($processInfo) || ($processInfo['finish_id'] ?? null) !== $finishId) {
                    continue;
                }

                if ($shouldReleaseLock) {
                    $lockFile = substr($pidFile, 0, -strlen('.pid'));

                    if (file_exists($lockFile)) {
                        @unlink($lockFile);
                    }
                }

                @unlink($pidFile);

                break 2;
            }
        }

        if ($exitCode === 0) {
            return Command::SUCCESS;
        } else {
            error('Cron task failed with exit code: ' . $exitCode);
            return $exitCode;
        }
    }

    /**
     * Get the directory that holds the scheduler's lock files
     *
     * @return string|null
     */
    protected function scheduleDirectory(): ?string
    {
        try {
            return storage_path('schedule');
        } catch (\Throwable) {
            return null;
        }
    }
}
