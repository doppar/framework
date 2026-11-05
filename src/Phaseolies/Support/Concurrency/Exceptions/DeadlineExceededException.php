<?php

namespace Phaseolies\Support\Concurrency\Exceptions;

class DeadlineExceededException extends \RuntimeException
{
    /**
     * @param float $timeout
     */
    public function __construct(protected readonly float $timeout)
    {
        parent::__construct("Task group deadline of {$timeout} seconds was exceeded.");
    }

    /**
     * Get the configured timeout in seconds.
     *
     * @return float
     */
    public function timeout(): float
    {
        return $this->timeout;
    }
}
