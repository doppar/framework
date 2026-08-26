<?php

namespace Phaseolies\Console\Commands\Cron;

use App\Schedule\Schedule;
use Cron\CronExpression;
use Phaseolies\Console\Schedule\Command;
use Phaseolies\Console\Schedule\ScheduledCommand;
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
    public function handle(): int
    {
        return $this->executeWithTiming(function() {
            $schedule = new Schedule();
            $schedule->schedule($schedule);

            $commands = $schedule->getCommands();

            if (empty($commands)) {
                $this->displayInfo('No scheduled commands are registered.');
                return Command::SUCCESS;
            }

            $table = new Table($this->output);
            $table->setHeaders([
                'Command',
                'Schedule',
                'Next Run',
                'Timezone',
                'Mode',
                'Constraints',
            ]);

            foreach ($commands as $command) {
                $table->addRow($this->commandRow($command));
            }

            $table->render();
            $this->newLine();
            $this->displaySuccess('Listed ' . count($commands) . ' scheduled commands');
            return Command::SUCCESS;
        });
    }

    /**
     * Build a display row for the schedule table.
     *
     * @param ScheduledCommand $command
     * @return array
     */
    private function commandRow(ScheduledCommand $command): array
    {
        return [
            $command->getCommand(),
            $this->formatSchedule($command),
            $this->formatNextRun($command),
            $this->resolveTimezone($command),
            $command->shouldRunInBackground() ? 'Background' : 'Foreground',
            $this->formatConstraints($command),
        ];
    }

    /**
     * Format the schedule column for a command.
     *
     * @param ScheduledCommand $command
     * @return string
     */
    private function formatSchedule(ScheduledCommand $command): string
    {
        if ($command->isSecondSchedule()) {
            $specificSeconds = $command->getSpecificSeconds();
            if (!empty($specificSeconds)) {
                sort($specificSeconds);

                return 'At seconds ' . implode(', ', $specificSeconds) . ' each minute';
            }

            $interval = $command->getSecondInterval();

            return sprintf('Every %d second%s', $interval, $interval === 1 ? '' : 's');
        }

        return $command->getExpression();
    }

    /**
     * Format the next run value for a command.
     *
     * @param ScheduledCommand $command
     * @return string
     */
    private function formatNextRun(ScheduledCommand $command): string
    {
        if ($command->isSecondSchedule()) {
            if (!empty($command->getSpecificSeconds())) {
                return $this->formatSpecificSecondNextRun($command);
            }

            return 'Daemon-managed';
        }

        try {
            $nextRun = (new CronExpression($command->getExpression()))
                ->getNextRunDate('now', 0, false, $this->resolveTimezone($command));

            return $nextRun->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return 'Unavailable';
        }
    }

    /**
     * Resolve the effective timezone for the command.
     *
     * @param ScheduledCommand $command
     * @return string
     */
    private function resolveTimezone(ScheduledCommand $command): string
    {
        if ($command->getTimezone() !== null) {
            return $command->getTimezone();
        }

        if (function_exists('config')) {
            return (string) config('app.timezone', 'UTC');
        }

        return 'UTC';
    }

    /**
     * Format command constraints into a compact summary.
     *
     * @param ScheduledCommand $command
     * @return string
     */
    private function formatConstraints(ScheduledCommand $command): string
    {
        $constraints = [];

        if ($command->withoutOverlapping) {
            $constraints[] = 'No overlap (' . $command->getMaxLockTime() . 'm)';
        }

        if ($command->getRateLimit() !== null) {
            $constraints[] = 'Throttle ' . $command->getRateLimit();
        }

        if ($command->getMaxRetries() > 0) {
            $constraints[] = sprintf(
                'Retry %dx/%ss',
                $command->getMaxRetries(),
                $command->getRetryDelay()
            );
        }

        if ($command->hasAdditionalConditions()) {
            $constraints[] = 'Time window';
        }

        if ($command->hasCustomCondition()) {
            $constraints[] = 'Custom condition';
        }

        if (!empty($command->getExcludedDates())) {
            $constraints[] = 'Exclude ' . $this->summarizeExcludedDates($command->getExcludedDates());
        }

        return empty($constraints) ? 'None' : implode('; ', $constraints);
    }

    /**
     * Summarize excluded dates so the table stays readable.
     *
     * @param array $dates
     * @return string
     */
    private function summarizeExcludedDates(array $dates): string
    {
        $dates = array_values(array_unique($dates));
        $visibleDates = array_slice($dates, 0, 2);

        if (count($dates) <= 2) {
            return implode(', ', $visibleDates);
        }

        return implode(', ', $visibleDates) . sprintf(' +%d more', count($dates) - 2);
    }

    /**
     * Estimate the next run for commands scheduled at specific seconds.
     *
     * @param ScheduledCommand $command
     * @return string
     */
    private function formatSpecificSecondNextRun(ScheduledCommand $command): string
    {
        $timezone = $this->resolveTimezone($command);
        $seconds = $command->getSpecificSeconds();

        sort($seconds);

        try {
            $now = new \DateTimeImmutable('now', new \DateTimeZone($timezone));
            $currentSecond = (int) $now->format('s');

            foreach ($seconds as $second) {
                if ($second > $currentSecond) {
                    return $now->setTime(
                        (int) $now->format('H'),
                        (int) $now->format('i'),
                        $second
                    )->format('Y-m-d H:i:s');
                }
            }

            $nextMinute = $now->modify('+1 minute');

            return $nextMinute
                ->setTime(
                    (int) $nextMinute->format('H'),
                    (int) $nextMinute->format('i'),
                    $seconds[0]
                )
                ->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return 'Daemon-managed';
        }
    }
}
