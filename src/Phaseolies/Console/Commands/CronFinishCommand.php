<?php

namespace Phaseolies\Console\Commands;

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
    protected function handle(): int
    {
        $finishId = $this->argument('finish_id');
        $shouldReleaseLock = (bool)$this->argument('release_lock');
        $exitCode = (int)$this->argument('exit_code');

        // Find and clean up the process
        $pidFiles = glob(sys_get_temp_dir() . "/doppar_cron_lock_*.pid");

        foreach ($pidFiles as $pidFile) {
            $processInfo = json_decode(file_get_contents($pidFile), true);

            if ($processInfo['finish_id'] === $finishId) {
                if ($shouldReleaseLock) {
                    $lockFile = str_replace('.pid', '', $pidFile);
                    if (file_exists($lockFile)) {
                        unlink($lockFile);
                    }
                }

                unlink($pidFile);
                break;
            }
        }

        if ($exitCode === 0) {
            return Command::SUCCESS;
        } else {
            error('Cron task failed with exit code: ' . $exitCode);
            return $exitCode;
        }
    }
}
