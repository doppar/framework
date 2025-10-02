<?php

namespace Phaseolies\Console\Commands;

use App\Schedule\Schedule;
use Phaseolies\Console\Schedule\Command;
use Symfony\Component\Console\Helper\Table;

class CronListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'cron:list';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'List all registered scheduled commands';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return $this->executeWithTiming(function() {
            $schedule = new Schedule();
            $schedule->schedule($schedule);

            $commands = $schedule->getCommands();

            if (empty($commands)) {
                $this->displayInfo('No scheduled commands are registered.');
                return 0;
            }

            $table = new Table($this->output);
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
            $this->newLine();
            $this->displaySuccess('Listed ' . count($commands) . ' scheduled commands');
            return 0;
        });
    }
}
