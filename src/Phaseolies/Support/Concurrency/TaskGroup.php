<?php

namespace Phaseolies\Support\Concurrency;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use Phaseolies\DI\Container;
use Phaseolies\Support\Concurrency\Exceptions\DeadlineExceededException;
use Phaseolies\Support\Concurrency\Exceptions\TaskCancelledException;
use Phaseolies\Support\Concurrency\Exceptions\TaskFailedException;

final class TaskGroup
{
    /**
     * Task groups currently owning the event loop.
     *
     * @var array<string, array<int, self>>
     */
    private static array $active = [];

    /**
     * Tasks keyed by their unique names.
     *
     * @var array<string, Task>
     */
    private array $tasks = [];

    /** Whether this group has started. */
    private bool $started = false;

    /** Whether every task has settled. */
    private bool $finished = false;

    /** Primary failure that ended the group. */
    private ?\Throwable $failure = null;

    /** Name of the task that produced the primary failure. */
    private ?string $failedTask = null;

    /** Shared deadline timer for the group. */
    private ?TimerInterface $deadlineTimer = null;

    /** Event loop used to schedule this group. */
    private LoopInterface $loop;

    /** Fiber waiting while an already-running loop owns execution. */
    private ?\Fiber $waitingFiber = null;

    /** Execution-context identifier that owns this group. */
    private ?string $scopeId = null;

    /** Whether this group started and therefore owns the loop run. */
    private bool $drivesLoop = false;

    /**
     * @param float|null $timeout Maximum runtime in seconds
     * @param LoopInterface|null $loop
     */
    public function __construct(
        private readonly ?float $timeout = null,
        ?LoopInterface $loop = null
    ) {
        if ($timeout !== null && $timeout < 0) {
            throw new \InvalidArgumentException('A task group timeout cannot be negative.');
        }

        $this->loop = $loop ?? self::resolveLoop();
    }

    /**
     * Run a named set of tasks and return their results.
     *
     * @param array<string, callable> $tasks
     * @param float|null $timeout Maximum runtime in seconds
     * @param LoopInterface|null $loop
     * @return array<string, mixed>
     */
    public static function run(
        array $tasks,
        ?float $timeout = null,
        ?LoopInterface $loop = null
    ): array {
        $group = new self($timeout, $loop);

        foreach ($tasks as $name => $task) {
            $group->spawn((string) $name, $task);
        }

        return $group->await();
    }

    /**
     * Add a named task to the group before it starts.
     *
     * @param string $name
     * @param callable $task
     * @return $this
     */
    public function spawn(string $name, callable $task): static
    {
        if ($this->started) {
            throw new \LogicException('Tasks cannot be added after a task group has started.');
        }

        if (trim($name) === '') {
            throw new \InvalidArgumentException('A task name cannot be empty.');
        }

        if (isset($this->tasks[$name])) {
            throw new \InvalidArgumentException("Task [{$name}] is already registered.");
        }

        $this->tasks[$name] = new Task($name, $task, $this);

        return $this;
    }

    /**
     * Start every task and wait until the group settles.
     *
     * @return array<string, mixed>
     *
     * @throws TaskFailedException
     * @throws DeadlineExceededException
     */
    public function await(): array
    {
        if ($this->started) {
            throw new \LogicException('A task group may only be awaited once.');
        }

        $this->started = true;

        if ($this->tasks === []) {
            $this->finished = true;

            return [];
        }

        $this->scopeId = self::currentScopeId();

        if ((self::$active[$this->scopeId] ?? []) !== []) {
            throw new \LogicException('Nested task groups are not supported.');
        }

        $id = spl_object_id($this);
        self::$active[$this->scopeId][$id] = $this;

        try {
            if ($this->timeout !== null) {
                $this->deadlineTimer = $this->loop->addTimer($this->timeout, function (): void {
                    $this->deadlineExceeded();
                });
            }

            $this->loop->futureTick(function (): void {
                foreach ($this->tasks as $task) {
                    $task->start();
                }
            });

            $currentFiber = \Fiber::getCurrent();

            if ($currentFiber !== null) {
                $this->waitingFiber = $currentFiber;
                \Fiber::suspend();
            } else {
                $this->drivesLoop = true;
                $this->loop->run();
            }

            if (!$this->finished) {
                $failure = new \RuntimeException(
                    'The event loop stopped before every task in the group settled.'
                );
                $this->cancel($failure);
                $this->failure = $failure;
            }

            if ($this->failure instanceof DeadlineExceededException) {
                throw $this->failure;
            }

            if ($this->failure !== null && $this->failedTask !== null) {
                throw new TaskFailedException($this->failedTask, $this->failure);
            }

            if ($this->failure !== null) {
                throw $this->failure;
            }

            $results = [];

            foreach ($this->tasks as $name => $task) {
                $results[$name] = $task->result();
            }

            return $results;
        } finally {
            $this->cancelDeadlineTimer();
            unset(self::$active[$this->scopeId][$id]);

            if (self::$active[$this->scopeId] === []) {
                unset(self::$active[$this->scopeId]);
            }
        }
    }

    /**
     * Schedule a suspended task to resume with a value.
     *
     * @param Task $task
     * @param mixed $value
     * @return void
     * @internal
     */
    public function resume(Task $task, mixed $value): void
    {
        $task->resumeWith($value);
    }

    /**
     * Schedule an asynchronous failure inside a suspended task.
     *
     * @param Task $task
     * @param \Throwable $failure
     * @return void
     * @internal
     */
    public function reject(Task $task, \Throwable $failure): void
    {
        $task->rejectWith($failure);
    }

    /**
     * Record a terminal task and settle or cancel the group as needed.
     *
     * @param Task $task
     * @return void
     * @internal
     */
    public function settled(Task $task): void
    {
        $failure = $task->failure();

        if ($failure !== null && !$failure instanceof TaskCancelledException && $this->failure === null) {
            $this->failure = $failure;
            $this->failedTask = $task->name();
            $this->cancel($failure, $task);
        }

        foreach ($this->tasks as $candidate) {
            if (!$candidate->terminal()) {
                return;
            }
        }

        $this->finished = true;
        $this->cancelDeadlineTimer();

        if ($this->waitingFiber !== null) {
            $waitingFiber = $this->waitingFiber;
            $this->loop->futureTick(function () use ($waitingFiber): void {
                if ($waitingFiber->isSuspended()) {
                    $waitingFiber->resume();
                }
            });
        } elseif ($this->drivesLoop) {
            $this->loop->stop();
        }
    }

    /**
     * Cancel every unsettled task in the group.
     *
     * @param \Throwable|null $reason
     * @param Task|null $except
     * @return void
     */
    public function cancel(?\Throwable $reason = null, ?Task $except = null): void
    {
        if ($except === null && $this->failure === null) {
            $this->failure = $reason ?? new \RuntimeException('The task group was cancelled.');
        }

        foreach ($this->tasks as $task) {
            if ($task !== $except) {
                $task->cancel($reason);
            }
        }
    }

    /**
     * Cancel every task group still active in the current process.
     *
     * @param \Throwable|null $reason
     * @param string|null $scopeId
     * @return void
     */
    public static function cancelPending(?\Throwable $reason = null, ?string $scopeId = null): void
    {
        $scopeId ??= self::currentScopeId();

        foreach (self::$active[$scopeId] ?? [] as $group) {
            $group->cancel($reason);
        }
    }

    /**
     * Ensure no child task group remains active before a request finishes.
     *
     * @param string|null $scopeId
     * @return void
     */
    public static function assertNothingPending(?string $scopeId = null): void
    {
        $scopeId ??= self::currentScopeId();

        if ((self::$active[$scopeId] ?? []) !== []) {
            throw new \LogicException('A request cannot finish while child tasks remain registered.');
        }
    }

    /**
     * Fail the group when its shared deadline expires.
     *
     * @return void
     */
    private function deadlineExceeded(): void
    {
        if ($this->finished || $this->failure !== null) {
            return;
        }

        $this->failure = new DeadlineExceededException($this->timeout ?? 0.0);
        $this->cancel($this->failure);
    }

    /**
     * Cancel the shared deadline timer once the group settles.
     *
     * @return void
     */
    private function cancelDeadlineTimer(): void
    {
        if ($this->deadlineTimer === null) {
            return;
        }

        $this->loop->cancelTimer($this->deadlineTimer);
        $this->deadlineTimer = null;
    }

    /**
     * Resolve the configured event loop, falling back to React's default loop.
     *
     * @return LoopInterface
     */
    private static function resolveLoop(): LoopInterface
    {
        $container = Container::getInstance();

        if ($container->scopeOf(LoopInterface::class) !== null) {
            return $container->make(LoopInterface::class);
        }

        return Loop::get();
    }

    /**
     * Resolve the current execution-context identifier.
     *
     * @return string
     */
    private static function currentScopeId(): string
    {
        $context = Container::getInstance()->executionContext();

        return $context?->id() ?? 'standalone';
    }
}
