<?php

namespace Phaseolies\Error\Contracts;

use Throwable;

interface ErrorHandlerInterface
{
    /**
     * Handle the given exception or error.
     *
     * @param Throwable $exception
     * @return void
     */
    public function handle(Throwable $exception): void;

    /**
     * Determine if this handler supports the current context.
     *
     * @return bool
     */
    public function supports(): bool;
}
