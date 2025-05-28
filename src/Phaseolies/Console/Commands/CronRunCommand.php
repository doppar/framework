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

            if ($command->shouldRunInBackground()) {
                $process = new Process(['php', 'pool', $command->getCommand()]);
                $process->start();
                $output->writeln('<info>Scheduled command started in background:</info> ' . $command->getCommand());
            } else {
                $process = new Process(['php', 'pool', $command->getCommand()]);
                $process->run();
                
                if ($process->isSuccessful()) {
                    $output->writeln('<info>Success:</info> ' . $command->getCommand());
                } else {
                    $output->writeln('<error>Error:</error> ' . $command->getCommand());
                    $output->writeln('<error>Output:</error> ' . $process->getErrorOutput());
                }
            }
        }

        return Command::SUCCESS;
    }
}