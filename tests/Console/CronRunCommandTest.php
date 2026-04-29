<?php

namespace Tests\Unit\Console;

use Phaseolies\Console\Commands\Cron\CronRunCommand;
use PHPUnit\Framework\TestCase;

class CronRunCommandTest extends TestCase
{
    public function testProcessSecondBasedCommandExecutesOnFirstRun(): void
    {
        $runner = new SpyCronRunCommand();
        $command = new FakeSecondBasedCommand('emails:send', 5);

        $runner->processAt($command, 100);

        $this->assertSame([['emails:send', true]], $runner->executedCommands);
        $this->assertSame([md5('emails:send') => 100], $runner->lastExecutionMap());
        $this->assertStringContainsString('first run', $runner->infoMessages[0]);
    }

    public function testProcessSecondBasedCommandWaitsForIntervalBeforeReRunning(): void
    {
        $runner = new SpyCronRunCommand();
        $command = new FakeSecondBasedCommand('reports:sync', 5);

        $runner->processAt($command, 100);
        $runner->processAt($command, 103);
        $runner->processAt($command, 105);

        $this->assertCount(2, $runner->executedCommands);
        $this->assertSame([['reports:sync', true], ['reports:sync', true]], $runner->executedCommands);
        $this->assertStringContainsString('interval: 5s', $runner->infoMessages[1]);
    }

    public function testProcessSecondBasedCommandFallsBackToIsDueWhenIntervalIsUnavailable(): void
    {
        $runner = new SpyCronRunCommand();
        $command = new FakeSecondBasedCommand('heartbeat:check', null, true);

        $runner->processAt($command, 200);
        $runner->processAt($command, 200);
        $runner->processAt($command, 201);

        $this->assertSame(
            [['heartbeat:check', true], ['heartbeat:check', true]],
            $runner->executedCommands
        );

        $notDue = new FakeSecondBasedCommand('heartbeat:skip', null, false);
        $runner->processAt($notDue, 202);

        $this->assertCount(2, $runner->executedCommands);
    }
}

final class SpyCronRunCommand extends CronRunCommand
{
    public array $executedCommands = [];

    public array $infoMessages = [];

    public function processAt(object $command, int $timestamp): void
    {
        $this->processSecondBasedCommand($command, $timestamp);
    }

    public function lastExecutionMap(): array
    {
        return $this->lastExecution;
    }

    protected function executeCommand($command, bool $isSecondBased = false): void
    {
        $this->executedCommands[] = [$command->getCommand(), $isSecondBased];
    }

    protected function displayInfo(string $message): void
    {
        $this->infoMessages[] = $message;
    }
}

final class FakeSecondBasedCommand
{
    public function __construct(
        private string $command,
        private ?int $interval,
        private bool $due = true
    ) {
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getSecondInterval(): ?int
    {
        return $this->interval;
    }

    public function isDue(): bool
    {
        return $this->due;
    }
}
