<?php

namespace Tests\Console\Support;

use Phaseolies\Console\Commands\Cron\CronRunCommand;
use Phaseolies\Console\Schedule\ScheduledCommand;

/**
 * The real CronRunCommand, with the console plumbing replaced so a test can
 * hand it options and a schedule and read back what it printed.
 */
class ScratchCronRunCommand extends CronRunCommand
{
    /** @var array<int, ScheduledCommand> */
    public array $scheduled = [];

    /** @var array<string, mixed> */
    public array $givenOptions = [];

    /** @var array<int, string> */
    public array $messages = [];

    public bool $canStartDetached = true;

    protected function option($key = null)
    {
        return $key === null ? $this->givenOptions : ($this->givenOptions[$key] ?? null);
    }

    protected function makeSchedule(): ?object
    {
        if ($this->scheduled === [] && !class_exists('App\\Schedule\\Schedule')) {
            return parent::makeSchedule();
        }

        return new class ($this->scheduled) {
            public function __construct(private array $commands)
            {
            }

            public function getCommands(): array
            {
                return $this->commands;
            }
        };
    }

    protected function startDetached(string $shellCommand): ?int
    {
        return $this->canStartDetached ? parent::startDetached($shellCommand) : null;
    }

    protected function executeWithTiming(callable $callback): int
    {
        return $callback();
    }

    protected function info($string): void
    {
        $this->messages[] = "info: {$string}";
    }

    protected function line(string $string, ?string $style = null): void
    {
        $this->messages[] = "line: {$string}";
    }

    protected function error($string): void
    {
        $this->messages[] = "error: {$string}";
    }

    protected function newLine($count = 1): void
    {
    }

    protected function displayInfo(string $message): void
    {
        $this->messages[] = "info: {$message}";
    }

    protected function displayWarning(string $message): void
    {
        $this->messages[] = "warn: {$message}";
    }

    protected function displayError(string $message): void
    {
        $this->messages[] = "error: {$message}";
    }

    protected function displaySuccess(string $message): void
    {
        $this->messages[] = "ok: {$message}";
    }

    public function said(string $needle): bool
    {
        foreach ($this->messages as $message) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function buildBackground(string $command, string $log, string $finishId, bool $release, array $env = []): string
    {
        return $this->buildBackgroundCommand($command, $log, $finishId, $release, $env);
    }

    public function runProcessInProcess(string $command): array
    {
        return $this->runInProcess($command);
    }
}
