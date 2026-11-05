<?php

namespace Phaseolies\Support\Concurrency\Exceptions;

class TaskCancelledException extends \RuntimeException
{
    /**
     * Create a cancellation exception for a named task.
     *
     * @param string $taskName
     * @param \Throwable|null $reason
     */
    public function __construct(string $taskName, ?\Throwable $reason = null)
    {
        parent::__construct("Task [{$taskName}] was cancelled.", 0, $reason);
    }
}
