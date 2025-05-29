<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command;

class CronFinishCommand extends Command
{
    protected static $defaultName = 'cron:finish';

    protected function configure()
    {
        $this
            ->setName('cron:finish')
            ->setDescription('Handle completion of scheduled commands')
            ->addArgument('finish_id', InputArgument::REQUIRED, 'The finish identifier')
            ->addArgument('release_lock', InputArgument::REQUIRED, 'Whether to release lock')
            ->addArgument('exit_code', InputArgument::REQUIRED, 'The exit code of the command');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $finishId = $input->getArgument('finish_id');
        $shouldReleaseLock = (bool)$input->getArgument('release_lock');
        $exitCode = (int)$input->getArgument('exit_code');

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

        return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
