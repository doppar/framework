<?php

namespace Phaseolies\Error\Handlers;

use Throwable;
use Symfony\Component\Console\Output\ConsoleOutput;
use Phaseolies\Error\Contracts\ErrorHandlerInterface;

class CliErrorHandler implements ErrorHandlerInterface
{
    /**
     * Outputs formatted error information to the console.
     *
     * @param Throwable $exception
     * @return void
     */
    public function handle(Throwable $exception): void
    {
        $output = new ConsoleOutput();
        $section = $output->section();

        $section->writeln([
            '',
            '<fg=red;options=bold><bg=red;fg=white;> ERROR </></>',
            ''
        ]);

        $section->writeln([
            sprintf('<fg=red;>⛔ ERROR:</> <fg=red>%s</>', $exception->getMessage()),
            sprintf('<fg=red>📄 FILE:</> <fg=white>%s</>', $exception->getFile()),
            sprintf('<fg=red>📌 LINE:</> <fg=white>%d</>', $exception->getLine()),
        ]);

        exit(1);
    }

    /**
     * Checks if this handler should be used (CLI mode).
     *
     * @return bool
     */
    public function supports(): bool
    {
        return PHP_SAPI === 'cli' || defined('STDIN');
    }
}
