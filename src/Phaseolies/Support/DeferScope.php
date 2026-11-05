<?php

namespace Phaseolies\Support;

class DeferScope
{
    /**
     * Registered callbacks, in registration order.
     *
     * @var array<int, callable>
     */
    protected array $callbacks = [];

    /**
     * Indicates whether the callbacks have already been run.
     *
     * @var bool
     */
    protected bool $closed = false;

    /**
     * Indicates whether the callbacks are currently running.
     *
     * @var bool
     */
    protected bool $running = false;

    /**
     * Register a callback to run when the scope ends.
     *
     * @param callable $callback
     * @return void
     */
    public function push(callable $callback): void
    {
        $this->callbacks[] = $callback;
    }

    /**
     * Determine if the scope has already run its callbacks.
     *
     * @return bool
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * Run every callback in reverse registration order.
     *
     * @return array<int, \Throwable>
     */
    public function run(): array
    {
        if ($this->closed || $this->running) {
            return [];
        }

        $this->running = true;
        $failures = [];

        while ($this->callbacks !== []) {
            $callback = array_pop($this->callbacks);

            try {
                $callback();
            } catch (\Throwable $e) {
                $failures[] = $e;
            }
        }

        $this->running = false;
        $this->closed = true;

        return $failures;
    }
}
