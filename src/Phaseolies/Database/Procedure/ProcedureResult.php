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
     * The stored procedure results.
     * This is a multidimensional array where each element represents a result set,
     * which itself is an array of rows (associative arrays).
     *
     * @var array
     */
    protected $results = [];

    /**
     * Current result set being accessed
     *
     * @var int
     */
    protected $currentSet = 0;

    /**
     * Current row being accessed
     *
     * @var int
     */
    protected $currentRow = 0;

    /**
     * Initialize the ProcedureResult with an array of result sets.
     *
     * @param array
     */
    public function __construct(array $results)
    {
        $this->results = $results;
    }

    /**
     * Determine if an offset exists in the current row of the current result set.
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->getCurrentData()[$offset]);
    }

    /**
     * Retrieve a value by offset from the current row of the current result set.
     *
     * @param mixed $offset
     * @return mixed|null
     */
    public function offsetGet($offset): mixed
    {
        return $this->getCurrentData()[$offset] ?? null;
    }

    /**
     * Set a value by offset on the current row of the current result set.
     *
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        $currentData = &$this->getCurrentDataReference();

        $currentData[$offset] = $value;
    }

    /**
     * Unset a value by offset on the current row of the current result set.
     *
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        $currentData = &$this->getCurrentDataReference();

        unset($currentData[$offset]);
    }

    /**
     * Get a property from the current row.
     *
     * @param string $name
     * @return mixed|null
     */
    public function __get($name)
    {
        return $this->getCurrentData()[$name] ?? null;
    }

    /**
     * Set a property on the current row.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set($name, $value)
    {
        $currentData = &$this->getCurrentDataReference();

        $currentData[$name] = $value;
    }

    /**
     * Check if a property is set on the current row.
     *
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->getCurrentData()[$name]);
    }

    /**
     * Unset a property on the current row.
     *
     * @param string $name
     * @return void
     */
    public function __unset($name)
    {
        $currentData = &$this->getCurrentDataReference();

        unset($currentData[$name]);
    }

    /**
     * Get an iterator for the current row's data.
     *
     * @return Traversable
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->getCurrentData());
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        return $this->getCurrentData();
    }

    /**
     * Retrieve all rows from the first result set.
     *
     * @return Collection
     */
    public function all(): Collection
    {
        return new Collection('array', $this->results[0] ?? []);
    }

    /**
     * Retrieve the first row from the first result set.
     *
     * @return Collection|null
     */
    public function first(): ?Collection
    {
        return new Collection('array', $this->results[0][0] ?? []);
    }

    /**
     * Retrieve the last row from the first result set.
     *
     * @return Collection|null
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
     * Retrieve the last row from a specified result set.
     *
     * @param int $setIndex
     * @return Collection|null
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
     * Retrieve a specific result set by index.
     *
     * @param int $index
     * @return array<int, array<string, mixed>>
     */
    public function resultSet(int $index): array
    {
        return $this->results[$index] ?? [];
    }

    /**
     * Get the current row data from the current result set.
     *
     * @return array<string, mixed>
     */
    protected function getCurrentData(): array
    {
        return $this->results[$this->currentSet][$this->currentRow] ?? [];
    }

    /**
     * Get a reference to the current row data for modification.
     *
     * This method ensures that the current result set and row exist,
     * initializing them as empty arrays if necessary.
     *
     * @return array<string, mixed>
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
