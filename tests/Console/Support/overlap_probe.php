<?php

/**
 * Child process used by CronRunProcessTest: asks a noOverlap() task whether it
 * may run, then (if it may) stays alive so the others can see the live lock.
 *
 * Usage: php overlap_probe.php <app root> <start_at microtime>
 */

require __DIR__ . '/../../../vendor/autoload.php';

use Phaseolies\Console\Schedule\ScheduledCommand;
use Phaseolies\DI\Container;
use Tests\Console\Support\ScratchAppContainer;

Container::setInstance((new ScratchAppContainer())->setRoot($argv[1]));

$task = (new ScheduledCommand('slow 1'))->everyMinute()->timezone('UTC')->noOverlap();

// All probes ask at the same instant, to force the race.
while (microtime(true) < (float) $argv[2]) {
    usleep(100);
}

$due = $task->isDue();

echo $due ? "RUN\n" : "SKIP\n";

if ($due) {
    // Hold the lock for a while, as a running job would.
    sleep(3);
}
