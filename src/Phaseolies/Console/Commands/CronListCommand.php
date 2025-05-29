<?php

namespace Phaseolies\Console\Commands;

use App\Schedule\Schedule;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;

class CronListCommand extends Command
{
    protected static $defaultName = 'cron:list';

    protected function configure()
    {
        $this
            ->setName('cron:list')
            ->setDescription('List all registered scheduled commands');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schedule = new Schedule();
        $schedule->schedule($schedule);

        $commands = $schedule->getCommands();

        if (empty($commands)) {
            $output->writeln('<info>No scheduled commands are registered.</info>');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders([
            'Command',
            'Runs In',
            'Without Overlapping'
        ]);

        foreach ($commands as $command) {
            $table->addRow([
                $command->getCommand(),
                $command->shouldRunInBackground() ? 'Background' : 'Foreground',
                $command->withoutOverlapping ? 'Yes' : 'No'
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
