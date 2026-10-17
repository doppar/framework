<?php

namespace Phaseolies\DI\Concerns;

use Phaseolies\Support\ExecutionContext;

trait InteractsWithExecutionContexts
{
    /**
     * Active contexts, innermost last.
     *
     * @var array<int, ExecutionContext>
     */
    private array $contextStack = [];

    /**
     * Contexts that have begun and not yet ended, including suspended ones
     *
     * @var array<string, ExecutionContext>
     */
    private array $liveContexts = [];

    /**
     * Resolved execution-scoped instances, keyed by context id then abstract.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $scopedInstances = [];

    /**
     * Lifecycle listeners.
     *
     * @var array{begin: array<int, array{callable, ?string}>, end: array<int, array{callable, ?string}>}
     */
    private array $contextListeners = ['begin' => [], 'end' => []];

    /**
     * Begin a context nested inside the current one (if any) and make it current.
     *
     * @param string $type
     * @param array<string, mixed> $metadata
     * @return ExecutionContext
     */
    public function beginContext(string $type, array $metadata = []): ExecutionContext
    {
        $context = ExecutionContext::create($type, $metadata, $this->executionContext());

        $this->contextStack[] = $context;
        $this->liveContexts[$context->id()] = $context;

        try {
            $this->fireContextListeners('begin', $context);
        } catch (\Throwable $e) {
            $this->endContext($context);

            throw $e;
        }

        return $context;
    }

    /**
     * End a context: end any contexts nested inside it, run the end
     *
     * @param ExecutionContext $context
     * @return void
     *
     * @throws \Throwable
     */
    public function endContext(ExecutionContext $context): void
    {
        $index = $this->contextIndex($context);

        if ($index === null) {
            if (!isset($this->liveContexts[$context->id()])) {
                return;
            }

            // Suspended: put its latest copy back on top so listeners and
            // teardown see it as current.
            $this->contextStack[] = $this->liveContexts[$context->id()];
            $index = count($this->contextStack) - 1;
        }

        $failure = null;

        $endingIds = [$context->id() => true];

        for ($i = $index; $i < count($this->contextStack); $i++) {
            $endingIds[$this->contextStack[$i]->id()] = true;
        }

        foreach ($this->suspendedDescendantsOf($endingIds) as $descendant) {
            if (!isset($this->liveContexts[$descendant->id()])) {
                continue;
            }

            try {
                $this->endContext($descendant);
            } catch (\Throwable $e) {
                $failure ??= $e;
            }
        }

        while (count($this->contextStack) > $index) {
            $ending = end($this->contextStack);

            try {
                $this->fireContextListeners('end', $ending);
            } catch (\Throwable $e) {
                $failure ??= $e;
            }

            unset($this->scopedInstances[$ending->id()], $this->liveContexts[$ending->id()]);
            array_pop($this->contextStack);
        }

        if ($failure !== null) {
            throw $failure;
        }
    }

    /**
     * Run a callback inside a fresh context that always ends afterwards.
     *
     * @param string $type
     * @param array<string, mixed> $metadata
     * @param callable $callback
     * @return mixed
     */
    public function runInContext(string $type, array $metadata, callable $callback): mixed
    {
        $context = $this->beginContext($type, $metadata);

        try {
            return $callback($context);
        } finally {
            $this->endContext($context);
        }
    }

    /**
     * Detach a context from the stack without ending it, so it can outlive the call that created it
     *
     * @param ExecutionContext $context
     * @return void
     */
    public function suspendContext(ExecutionContext $context): void
    {
        $index = $this->contextIndex($context);

        if ($index === null) {
            return;
        }

        while (count($this->contextStack) > $index + 1) {
            $this->endContext(end($this->contextStack));
        }

        array_pop($this->contextStack);
    }

    /**
     * Make a suspended context current again.
     *
     * @param ExecutionContext $context
     * @return void
     */
    public function resumeContext(ExecutionContext $context): void
    {
        // An ended context stays ended, and a context that is already
        // current needs no resuming.
        if (!isset($this->liveContexts[$context->id()]) || $this->contextIndex($context) !== null) {
            return;
        }

        $this->contextStack[] = $this->liveContexts[$context->id()];
    }

    /**
     * The innermost active context, if any.
     *
     * @return ExecutionContext|null
     */
    public function executionContext(): ?ExecutionContext
    {
        return $this->contextStack === [] ? null : end($this->contextStack);
    }

    /**
     * Whether there is an active context
     *
     * @return bool
     */
    public function hasExecutionContext(): bool
    {
        return $this->contextStack !== [];
    }

    /**
     * Merge metadata into the current context
     *
     * @param array<string, mixed> $metadata
     * @return ExecutionContext
     *
     * @throws \LogicException
     */
    public function stampContext(array $metadata): ExecutionContext
    {
        $current = $this->executionContext();

        if ($current === null) {
            throw new \LogicException('Cannot add metadata: no execution context is active.');
        }

        $stamped = $current->withMetadata($metadata);

        $this->contextStack[array_key_last($this->contextStack)] = $stamped;
        $this->liveContexts[$stamped->id()] = $stamped;

        return $stamped;
    }

    /**
     * Register a listener for contexts beginning.
     *
     * @param callable(ExecutionContext): void $listener
     * @param string|null $type
     * @return void
     */
    public function contextBeginning(callable $listener, ?string $type = null): void
    {
        $this->contextListeners['begin'][] = [$listener, $type];
    }

    /**
     * Register a listener for contexts ending.
     *
     * @param callable(ExecutionContext): void $listener
     * @param string|null $type
     * @return void
     */
    public function contextEnding(callable $listener, ?string $type = null): void
    {
        $this->contextListeners['end'][] = [$listener, $type];
    }

    /**
     * Get (or build and remember) the instance of an execution-scoped binding for the current context.
     *
     * @param string $abstract
     * @param callable $factory
     * @return mixed
     *
     * @throws \LogicException
     */
    private function executionScopedInstance(string $abstract, callable $factory): mixed
    {
        $context = $this->executionContext();

        if ($context === null) {
            throw new \LogicException(
                "Cannot resolve execution-scoped service [{$abstract}] outside an execution context. "
                . 'Resolve it during an HTTP dispatch, a console command or inside runInContext().'
            );
        }

        $id = $context->id();

        if (!array_key_exists($abstract, $this->scopedInstances[$id] ?? [])) {
            $this->scopedInstances[$id][$abstract] = $factory();
        }

        return $this->scopedInstances[$id][$abstract];
    }

    /**
     * Forget one abstract's instance in every context.
     *
     * @param string $abstract
     * @return void
     */
    private function forgetScopedInstance(string $abstract): void
    {
        foreach ($this->scopedInstances as $id => $instances) {
            unset($this->scopedInstances[$id][$abstract]);
        }
    }

    /**
     * End every context that is still open, innermost first and suspended ones last
     *
     * @return void
     */
    private function resetExecutionContexts(): void
    {
        $failure = null;

        try {
            while ($this->contextStack !== []) {
                try {
                    $this->endContext(end($this->contextStack));
                } catch (\Throwable $e) {
                    $failure ??= $e;
                }
            }

            foreach ($this->liveContexts as $suspended) {
                try {
                    $this->endContext($suspended);
                } catch (\Throwable $e) {
                    $failure ??= $e;
                }
            }
        } finally {
            $this->contextStack = [];
            $this->liveContexts = [];
            $this->scopedInstances = [];
            $this->contextListeners = ['begin' => [], 'end' => []];
        }

        if ($failure !== null) {
            throw $failure;
        }
    }

    /**
     * Find suspended contexts that were started inside any of the given contexts, deepest first
     *
     * @param array<string, true> $ancestorIds
     * @return array<int, ExecutionContext>
     */
    private function suspendedDescendantsOf(array $ancestorIds): array
    {
        $found = [];

        foreach ($this->liveContexts as $live) {
            if ($this->contextIndex($live) !== null) {
                continue;
            }

            for ($parent = $live->parent(); $parent !== null; $parent = $parent->parent()) {
                if (isset($ancestorIds[$parent->id()])) {
                    $found[] = $live;

                    break;
                }
            }
        }

        usort($found, fn (ExecutionContext $a, ExecutionContext $b) => $b->depth() <=> $a->depth());

        return $found;
    }

    /**
     * Get the index of a context in the stack, or null if it is not active
     *
     * @param ExecutionContext $context
     * @return int|null
     */
    private function contextIndex(ExecutionContext $context): ?int
    {
        foreach ($this->contextStack as $index => $active) {
            if ($active->id() === $context->id()) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Fire all listeners for a context event, skipping those that don't match the context type
     *
     * @param string $event
     * @param ExecutionContext $context
     * @return void
     */
    private function fireContextListeners(string $event, ExecutionContext $context): void
    {
        $failure = null;

        foreach ($this->contextListeners[$event] as [$listener, $type]) {
            if ($type !== null && $type !== $context->type()) {
                continue;
            }

            try {
                $listener($context);
            } catch (\Throwable $e) {
                $failure ??= $e;
            }
        }

        if ($failure !== null) {
            throw $failure;
        }
    }
}
