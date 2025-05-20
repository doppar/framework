<?php

namespace Phaseolies\Database\Procedure;

use Traversable;
use Phaseolies\Support\Collection;
use ArrayIterator;
use ArrayAccess;
use JsonSerializable;

class ProcedureResult implements ArrayAccess, JsonSerializable
{
    /**
     * The stored procedure results
     * @var array
     */
    protected $results = [];

    /**
     * Current result set being accessed
     * @var int
     */
    protected $currentSet = 0;

    /**
     * Current row being accessed
     * @var int
     */
    protected $currentRow = 0;

    public function __construct(array $results)
    {
        $this->results = $results;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->getCurrentData()[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->getCurrentData()[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $currentData = &$this->getCurrentDataReference();
        $currentData[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        $currentData = &$this->getCurrentDataReference();
        unset($currentData[$offset]);
    }

    public function __get($name)
    {
        return $this->getCurrentData()[$name] ?? null;
    }

    public function __set($name, $value)
    {
        $currentData = &$this->getCurrentDataReference();
        $currentData[$name] = $value;
    }

    public function __isset($name)
    {
        return isset($this->getCurrentData()[$name]);
    }

    public function __unset($name)
    {
        $currentData = &$this->getCurrentDataReference();
        unset($currentData[$name]);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->getCurrentData());
    }

    public function jsonSerialize(): mixed
    {
        return $this->getCurrentData();
    }

    /**
     * Get all results (nested)
     */
    public function all(): Collection
    {
        return new Collection('array', $this->results[0] ?? []);
    }

    /**
     * Get first row of first result set
     */
    public function first(): ?Collection
    {
        return new Collection('array', $this->results[0][0] ?? []);
    }

    /**
     * Get last row of first result set
     */
    public function last(): ?Collection
    {
        if (empty($this->results[0])) {
            return null;
        }

        $lastRow = end($this->results[0]);
        reset($this->results[0]);

        return new Collection('array', $lastRow ?: []);
    }

    /**
     * Get last row of specified result set
     */
    public function lastSet(int $setIndex): ?Collection
    {
        if (!isset($this->results[$setIndex]) || empty($this->results[$setIndex])) {
            return null;
        }

        $lastRow = end($this->results[$setIndex]);
        reset($this->results[$setIndex]);

        return new Collection('array', $lastRow ?: []);
    }

    /**
     * Get specific result set
     */
    public function resultSet(int $index): array
    {
        return $this->results[$index] ?? [];
    }

    /**
     * Get the current data being accessed
     */
    protected function getCurrentData(): array
    {
        return $this->results[$this->currentSet][$this->currentRow] ?? [];
    }

    /**
     * Get reference to current data for modification
     */
    protected function &getCurrentDataReference(): array
    {
        if (!isset($this->results[$this->currentSet])) {
            $this->results[$this->currentSet] = [];
        }

        if (!isset($this->results[$this->currentSet][$this->currentRow])) {
            $this->results[$this->currentSet][$this->currentRow] = [];
        }

        return $this->results[$this->currentSet][$this->currentRow];
    }
}
