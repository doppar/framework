<?php

namespace Phaseolies\Support\Concurrency;

use Phaseolies\Support\Concurrency\Exceptions\TaskCancelledException;

final class Task
{
    /** Pending task state. */
    private const PENDING = 'pending';

    /** Running task state. */
    private const RUNNING = 'running';

    /** Suspended task state. */
    private const SUSPENDED = 'suspended';

    /** Successful task state. */
    private const SUCCEEDED = 'succeeded';

    /** Failed task state. */
    private const FAILED = 'failed';

    /** Cancelled task state. */
    private const CANCELLED = 'cancelled';

    /** Current task state. */
    private string $state = self::PENDING;

    /** Fiber executing the task callback. */
    private ?\Fiber $fiber = null;

    /** Value returned by a successful task. */
    private mixed $result = null;

    /** Failure produced by the task. */
    private ?\Throwable $failure = null;

    /** Whether cancellation has been requested. */
    private bool $cancellationRequested = false;

    /** Cancellation delivered to the task. */
    private ?TaskCancelledException $cancellation = null;

    /** @var callable|null */
    private $cancelSuspension = null;

    /**
     * @param string $name
     * @param callable $callback
     * @param TaskGroup $group
     */
    public function __construct(
        private readonly string $name,
        /** @var callable */
        private readonly mixed $callback,
        private readonly TaskGroup $group
    ) {}

    /**
     * Get the task name.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Suspend the task until an asynchronous operation settles.
     *
     * The subscriber receives resolve and reject callbacks and may return a
     * cleanup callback that is invoked if the task is cancelled while waiting.
     *
     * @param callable(callable(mixed): void, callable(\Throwable): void): mixed $subscriber
     * @return mixed
     */
    public function await(callable $subscriber): mixed
    {
        if ($this->cancellationRequested) {
            throw $this->cancellation;
        }

        if (\Fiber::getCurrent() !== $this->fiber || $this->state !== self::RUNNING) {
            throw new \LogicException('Task::await() may only be called by its running task.');
        }

        $settled = false;
        $value = null;
        $error = null;

        $resolve = function (mixed $resolved = null) use (&$settled, &$value): void {
            if ($settled || $this->terminal()) {
                return;
            }

            $settled = true;
            $value = $resolved;

            if ($this->state === self::SUSPENDED) {
                $this->group->resume($this, $resolved);
            }
        };

        $reject = function (\Throwable $failure) use (&$settled, &$error): void {
            if ($settled || $this->terminal()) {
                return;
            }

            $settled = true;
            $error = $failure;

            if ($this->state === self::SUSPENDED) {
                $this->group->reject($this, $failure);
            }
        };

        $cleanup = $subscriber($resolve, $reject);

        if ($cleanup !== null && !is_callable($cleanup)) {
            throw new \LogicException('A task await subscriber must return a callable or null.');
        }

        if ($settled) {
            if ($error !== null) {
                throw $error;
            }

            return $value;
        }

        $this->cancelSuspension = $cleanup;
        $this->state = self::SUSPENDED;

        try {
            return \Fiber::suspend();
        } finally {
            $this->cancelSuspension = null;

            if (!$this->terminal()) {
                $this->state = self::RUNNING;
            }
        }
    }

    /**
     * Start executing the task.
     *
     * @return void
     */
    public function start(): void
    {
        if ($this->state !== self::PENDING) {
            return;
        }

        $this->fiber = new \Fiber(function (): mixed {
            return ($this->callback)($this);
        });

        $this->state = self::RUNNING;

        try {
            $this->fiber->start();
            $this->captureCompletion();
        } catch (\Throwable $failure) {
            $this->fail($failure);
        }
    }

    /**
     * Resume a suspended task with a resolved value.
     *
     * @param mixed $value
     * @return void
     */
    public function resumeWith(mixed $value): void
    {
        if ($this->state !== self::SUSPENDED || $this->fiber === null) {
            return;
        }

        $this->state = self::RUNNING;

        try {
            $this->fiber->resume($value);
            $this->captureCompletion();
        } catch (\Throwable $failure) {
            $this->fail($failure);
        }
    }

    /**
     * Resume a suspended task by throwing an asynchronous failure into it.
     *
     * @param \Throwable $failure
     * @return void
     */
    public function rejectWith(\Throwable $failure): void
    {
        if ($this->state !== self::SUSPENDED || $this->fiber === null) {
            return;
        }

        $this->state = self::RUNNING;

        try {
            $this->fiber->throw($failure);
            $this->captureCompletion();
        } catch (\Throwable $thrown) {
            $this->fail($thrown);
        }
    }

    /**
     * Cancel this task if it has not already settled.
     *
     * @param \Throwable|null $reason
     * @return void
     */
    public function cancel(?\Throwable $reason = null): void
    {
        if ($this->terminal()) {
            return;
        }

        $cancellation = new TaskCancelledException($this->name, $reason);

        $this->cancellationRequested = true;
        $this->cancellation = $cancellation;

        if ($this->state === self::SUSPENDED && $this->fiber !== null) {
            if ($this->cancelSuspension !== null) {
                try {
                    ($this->cancelSuspension)();
                } catch (\Throwable) {
                    // Cancellation cleanup must not replace the group failure.
                }
            }

            $this->rejectWith($cancellation);

            return;
        }

        $this->state = self::CANCELLED;
        $this->failure = $cancellation;
        $this->group->settled($this);
    }

    /**
     * Determine whether the task has reached a terminal state.
     *
     * @return bool
     */
    public function terminal(): bool
    {
        return in_array($this->state, [self::SUCCEEDED, self::FAILED, self::CANCELLED], true);
    }

    /**
     * Get the successful task result.
     *
     * @return mixed
     */
    public function result(): mixed
    {
        return $this->result;
    }

    /**
     * Get the task failure, if one exists.
     *
     * @return \Throwable|null
     */
    public function failure(): ?\Throwable
    {
        return $this->failure;
    }

    /**
     * Store a Fiber return value when execution has completed.
     *
     * @return void
     */
    private function captureCompletion(): void
    {
        if ($this->fiber === null || !$this->fiber->isTerminated()) {
            return;
        }

        if ($this->cancellationRequested) {
            $this->state = self::CANCELLED;
            $this->failure = $this->cancellation;
            $this->group->settled($this);

            return;
        }

        $this->result = $this->fiber->getReturn();
        $this->state = self::SUCCEEDED;
        $this->group->settled($this);
    }

    /**
     * Mark the task as failed.
     *
     * @param \Throwable $failure
     * @return void
     */
    private function fail(\Throwable $failure): void
    {
        if ($failure instanceof TaskCancelledException) {
            $this->state = self::CANCELLED;
        } else {
            $this->state = self::FAILED;
        }

        $this->failure = $failure;
        $this->group->settled($this);
    }
}
