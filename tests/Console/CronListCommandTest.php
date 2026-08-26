<?php

namespace Tests\Unit\Console;

use Phaseolies\Console\Commands\Cron\CronListCommand;
use Phaseolies\Console\Schedule\ScheduledCommand;
use PHPUnit\Framework\TestCase;

class CronListCommandTest extends TestCase
{
    public function testCommandRowIncludesCronExpressionNextRunAndTimezone(): void
    {
        $listCommand = new CronListCommand();
        $scheduledCommand = (new ScheduledCommand('reports:daily'))
            ->cron('*/15 * * * *')
            ->timezone('UTC');

        $row = $this->commandRow($listCommand, $scheduledCommand);

        $this->assertSame('reports:daily', $row[0]);
        $this->assertSame('*/15 * * * *', $row[1]);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row[2]);
        $this->assertSame('UTC', $row[3]);
        $this->assertSame('Foreground', $row[4]);
        $this->assertSame('None', $row[5]);
    }

    public function testCommandRowIncludesRuntimeConstraintsForSecondSchedules(): void
    {
        $listCommand = new CronListCommand();
        $scheduledCommand = (new ScheduledCommand('queue:prune'))
            ->everyFiveSeconds()
            ->inBackground()
            ->noOverlap(30)
            ->throttle('5/1h')
            ->retry(3, 120)
            ->exclude('2026-12-25', '2026-12-31', '2027-01-01')
            ->between('09:00', '17:00')
            ->when(fn() => true)
            ->timezone('UTC');

        $row = $this->commandRow($listCommand, $scheduledCommand);

        $this->assertSame('Every 5 seconds', $row[1]);
        $this->assertSame('Daemon-managed', $row[2]);
        $this->assertSame('UTC', $row[3]);
        $this->assertSame('Background', $row[4]);
        $this->assertStringContainsString('No overlap (30m)', $row[5]);
        $this->assertStringContainsString('Throttle 5/1h', $row[5]);
        $this->assertStringContainsString('Retry 3x/120s', $row[5]);
        $this->assertStringContainsString('Time window', $row[5]);
        $this->assertStringContainsString('Custom condition', $row[5]);
        $this->assertStringContainsString('Exclude 2026-12-25, 2026-12-31 +1 more', $row[5]);
    }

    public function testCommandRowEstimatesNextRunForSpecificSecondSchedules(): void
    {
        $listCommand = new CronListCommand();
        $scheduledCommand = (new ScheduledCommand('sync:heartbeat'))
            ->atSeconds([40, 10])
            ->timezone('UTC');

        $row = $this->commandRow($listCommand, $scheduledCommand);

        $this->assertSame('At seconds 10, 40 each minute', $row[1]);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row[2]);
    }

    private function commandRow(CronListCommand $listCommand, ScheduledCommand $scheduledCommand): array
    {
        $method = new \ReflectionMethod($listCommand, 'commandRow');

        return $method->invoke($listCommand, $scheduledCommand);
    }
}
