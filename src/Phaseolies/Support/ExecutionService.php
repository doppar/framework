<?php

namespace Phaseolies\Support;

use Phaseolies\DI\Container;

class ExecutionService
{
    /**
     * @param Container $container
     */
    public function __construct(protected Container $container) {}

    /**
     * The active context, or null outside one.
     *
     * @return ExecutionContext|null
     */
    public function current(): ?ExecutionContext
    {
        return $this->container->executionContext();
    }

    /**
     * Determine if we are currently running within an execution context.
     *
     * @return bool
     */
    public function running(): bool
    {
        return $this->container->hasExecutionContext();
    }

    /**
     * Get the ID of the active context, or null if not running within one.
     *
     * @return string|null
     */
    public function id(): ?string
    {
        return $this->current()?->id();
    }

    /**
     * Get the type of the active context, or null if not running within one.
     *
     * @return string|null
     */
    public function type(): ?string
    {
        return $this->current()?->type();
    }

    /**
     * Get a value from the active context, or return a default if not running within one.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $context = $this->current();

        return $context !== null && $context->has($key) ? $context->get($key) : $default;
    }

    /**
     * Get all values from the active context, or an empty array if not running within one.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->current()?->all() ?? [];
    }

    /**
     * Stamp metadata (a tenant id, a user id, ...) onto the active context.
     *
     * @param array<string, mixed> $metadata
     * @return ExecutionContext
     */
    public function stamp(array $metadata): ExecutionContext
    {
        return $this->container->stampContext($metadata);
    }

    /**
     * Run a callback within a nested context.
     *
     * @param string $type
     * @param array<string, mixed> $metadata
     * @param callable $callback
     * @return mixed
     */
    public function within(string $type, array $metadata, callable $callback): mixed
    {
        return $this->container->runInContext($type, $metadata, $callback);
    }

    /**
     * Register a listener to be called when a context of the given type begins.
     *
     * @param callable(ExecutionContext): void $listener
     * @param string|null $type
     * @return void
     */
    public function beginning(callable $listener, ?string $type = null): void
    {
        $this->container->contextBeginning($listener, $type);
    }

    /**
     * Register a listener to be called when a context of the given type ends.
     *
     * @param callable(ExecutionContext): void $listener
     * @param string|null $type
     * @return void
     */
    public function ending(callable $listener, ?string $type = null): void
    {
        $this->container->contextEnding($listener, $type);
    }
}
