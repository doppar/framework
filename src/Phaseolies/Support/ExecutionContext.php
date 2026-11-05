<?php

namespace Phaseolies\Support;

final class ExecutionContext
{
    public const REQUEST = 'request';

    public const CONSOLE = 'console';

    public const WORKER = 'worker';

    /**
     * @param string $id
     * @param string $type
     * @param array<string, mixed> $metadata
     * @param ExecutionContext|null $parent
     * @param float $startedAt
     */
    private function __construct(
        private readonly string $id,
        private readonly string $type,
        private readonly array $metadata,
        private readonly ?ExecutionContext $parent,
        private readonly float $startedAt
    ) {}

    /**
     * Create a new context, inheriting the parent's metadata when given.
     *
     * @param string $type
     * @param array<string, mixed> $metadata
     * @param ExecutionContext|null $parent
     * @return self
     */
    public static function create(string $type, array $metadata = [], ?self $parent = null): self
    {
        if ($type === '') {
            throw new \InvalidArgumentException('An execution context type cannot be empty.');
        }

        return new self(
            id: bin2hex(random_bytes(8)),
            type: $type,
            metadata: array_replace($parent?->all() ?? [], $metadata),
            parent: $parent,
            startedAt: microtime(true)
        );
    }

    /**
     * Return a copy of this context (same identity) with extra metadata merged in.
     *
     * @param array<string, mixed> $metadata
     * @return self
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            $this->id,
            $this->type,
            array_replace($this->metadata, $metadata),
            $this->parent,
            $this->startedAt
        );
    }

    /**
     * Unique identifier of this context, stable across withMetadata() copies.
     *
     * @return string
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Type of this context (request, console, worker, etc.).
     *
     * @return string
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Return the parent context, or null if this is a root context.
     *
     * @return ExecutionContext|null
     */
    public function parent(): ?self
    {
        return $this->parent;
    }

    /**
     * Number of ancestors above this context (0 for a root context).
     *
     * @return int
     */
    public function depth(): int
    {
        return $this->parent === null ? 0 : $this->parent->depth() + 1;
    }

    /**
     * Unix timestamp (with microseconds) at which the context began.
     *
     * @return float
     */
    public function startedAt(): float
    {
        return $this->startedAt;
    }

    /**
     * Get a metadata value from this context (or any ancestor), or return the default if not found.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->metadata) ? $this->metadata[$key] : $default;
    }

    /**
     * Check if a metadata key exists in this context (or any ancestor).
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->metadata);
    }

    /**
     * All metadata, including values inherited from ancestors.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->metadata;
    }

    /**
     * A flat, log-friendly representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'parent_id' => $this->parent?->id(),
            'metadata' => $this->metadata,
        ];
    }
}
