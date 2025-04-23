<?php

namespace Phaseolies\Database\Procedure;

class ProcedureResult
{
    protected $results;

    public function __construct(array $results)
    {
        $this->results = $results;
    }

    /**
     * Get all results (nested)
     */
    public function all(): array
    {
        return $this->results;
    }

    /**
     * Flatten the first result set
     */
    public function flatten(): array
    {
        return $this->results[0] ?? [];
    }

    /**
     * Get first row of first result set
     */
    public function first(): ?array
    {
        return $this->results[0][0] ?? null;
    }

    /**
     * Get specific result set
     */
    public function resultSet(int $index): array
    {
        return $this->results[$index] ?? [];
    }
}
