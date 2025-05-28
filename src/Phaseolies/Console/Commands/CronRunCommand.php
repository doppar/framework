<?php

namespace Phaseolies\Console\Commands;

use App\Schedule\Schedule;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;

class CronRunCommand extends Command
{
    protected static $defaultName = 'cron:run';

    protected function configure()
    {
        $this
            ->setName('cron:run')
            ->setDescription('Run the scheduled commands');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schedule = new Schedule();
        $schedule->schedule($schedule);

        $commandsToRun = $schedule->dueCommands();

        if (empty($commandsToRun)) {
            $output->writeln('<info>No scheduled commands are ready to run.</info>');
            return Command::SUCCESS;
        }

        foreach ($commandsToRun as $command) {
            $output->writeln('<comment>Running:</comment> ' . $command->getCommand());

            $env = array_merge(getenv(), [
                'APP_RUNNING_IN_CONSOLE' => true,
                'APP_SCHEDULE_RUNNING' => true
            ]);

            if ($command->shouldRunInBackground()) {
                $this->runInBackground($command, $output, $env);
            } else {
                $this->runInForeground($command, $output, $env);
            }
        }

        return Command::SUCCESS;
    }

    protected function runInBackground($command, $output, $env): void
    {
        $logFile = storage_path('logs/'.md5($command->getCommand()).'.log');
        $commandString = sprintf(
            'php pool %s >> %s 2>&1',
            $command->getCommand(),
            $logFile
        );

        if ($command->withoutOverlapping) {
            $command->lock();
        }

        $process = new Process(['nohup', 'sh', '-c', $commandString], base_path(), $env);
        $process->setTimeout(null);
        $process->start();

        file_put_contents(
            $command->getLockFile().'.pid',
            $process->getPid()
        );

        $output->writeln(sprintf(
            '<info>Background process started (PID: %d, Log: %s)</info>',
            $process->getPid(),
            $logFile
        ));
    }

    protected function runInForeground($command, $output, $env): void
    {
        if ($command->withoutOverlapping) {
            $command->lock();
        }

        $process = new Process(['php', 'pool', $command->getCommand()], base_path(), $env);
        $process->setTimeout(null);
        $process->run();

        if ($process->isSuccessful()) {
            $output->writeln('<info>Success:</info> ' . $command->getCommand());
        } else {
            $output->writeln('<error>Error:</error> ' . $command->getCommand());
            $output->writeln('<error>Output:</error> ' . $process->getErrorOutput());
        }

        if ($command->withoutOverlapping) {
            $command->releaseLock();
        }
    }
}