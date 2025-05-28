<?php

namespace Phaseolies\Console\Schedule;

use DateTime;
use Dragonmantank\CronExpression\CronExpression;

class ScheduledCommand
{
    private $command;
    private $expression = '* * * * *';
    private $withoutOverlapping = false;
    private $runInBackground = false;
    private $lastRunFile;

    public function __construct(string $command)
    {
        $this->command = $command;
        $this->lastRunFile = sys_get_temp_dir() . '/doppar_cron_' . md5($this->command);
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function everyFiveMinutes(): self
    {
        return $this->cron('*/5 * * * *');
    }

    public function dailyAt(string $time): self
    {
        $parts = explode(':', $time);
        $hour = $parts[0];
        $minute = $parts[1] ?? '0';
        
        return $this->cron("{$minute} {$hour} * * *");
    }

    public function cron(string $expression): self
    {
        $this->expression = $expression;
        return $this;
    }

    public function withoutOverlapping(): self
    {
        $this->withoutOverlapping = true;
        return $this;
    }

    public function runInBackground(): self
    {
        $this->runInBackground = true;
        return $this;
    }

    public function shouldRunInBackground(): bool
    {
        return $this->runInBackground;
    }

    public function isDue(): bool
    {
        if ($this->withoutOverlapping && $this->isLocked()) {
            return false;
        }

        $cron = new CronExpression($this->expression);
        return $cron->isDue();
    }

    private function isLocked(): bool
    {
        if (!file_exists($this->lastRunFile)) {
            return false;
        }

        $lastRun = file_get_contents($this->lastRunFile);
        return time() - (int)$lastRun < 3600; // Lock for 1 hour
    }

    private function lock(): void
    {
        file_put_contents($this->lastRunFile, time());
    }

    private function unlock(): void
    {
        if (file_exists($this->lastRunFile)) {
            unlink($this->lastRunFile);
        }
    }
}