<?php

namespace Phaseolies\Support\Concurrency\Exceptions;

class TaskFailedException extends \RuntimeException
{
    /**
     * @param string $taskName
     * @param \Throwable $failure
     */
    public function __construct(
        protected readonly string $taskName,
        \Throwable $failure
    ) {
        parent::__construct(
            "Task [{$taskName}] failed: {$failure->getMessage()}",
            0,
            $failure
        );
    }

    /**
     * Get the name of the failed task.
     *
     * @return string
     */
    public function taskName(): string
    {
        return $this->taskName;
    }
}
