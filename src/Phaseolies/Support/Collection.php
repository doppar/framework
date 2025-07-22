<?php

namespace Phaseolies\Support;

use Traversable;
use Ramsey\Collection\Collection as RamseyCollection;
use Phaseolies\Database\Eloquent\Model;
use IteratorAggregate;
use ArrayIterator;
use ArrayAccess;

class Collection extends RamseyCollection implements IteratorAggregate, ArrayAccess
{
    /**
     * @var array
     */
    protected array $data = [];

    /**
     * @var string
     */
    protected $model;

    /**
     * @param string $model
     * @param array|null $data
     */
    public function __construct(string $model, ?array $data = [])
    {
        $this->model = $model;
        $this->data = $data;
    }

    /**
     * Determines if the specified offset exists in the data array.
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    /**
     * Returns the value at the specified offset, or null if it doesn't exist.
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    /**
     * Sets a value at the specified offset in the data array.
     *
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        $this->data[$offset] = $value;
    }

    /**
     * Removes the value at the specified offset from the data array.
     *
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    /**
     * Required for looping data
     * @return Traversable
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }

    /**
     * Count the number of data in the collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Get all data in the collection
     *
     * @return array
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Get the first item in the collection
     *
     * @return mixed
     */
    public function first(): mixed
    {
        return $this->data[0] ?? null;
    }

    /**
     * Key the collection by the given key
     *
     * @param string $key
     * @return array
     */
    public function keyBy(string $key): array
    {
        $result = [];
        foreach ($this->data as $item) {
            $result[$item->$key] = $item;
        }
        return $result;
    }

    /**
     * Group the collection by the given key
     *
     * @param string $key
     * @return array
     */
    public function groupBy(string $key): array
    {
        $result = [];
        foreach ($this->data as $item) {
            $result[$item->$key][] = $item;
        }
        return $result;
    }

    /**
     * Convert the collection to an array
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_map(function ($item) {
            return $item instanceof Model ? $item->toArray() : $item;
        }, $this->data);
    }

    /**
     * Apply a callback to each item in the collection.
     *
     * @param callable $callback
     * @return static
     */
    public function map(callable $callback): self
    {
        $mappedItems = [];

        foreach ($this->data as $item) {
            $mappedItems[] = $callback($item);
        }

        return new static($this->model, $mappedItems);
    }

    /**
     * Filter the collection using the given callback.
     *
     * @param callable $callback
     * @return static
     */
    public function filter(callable $callback): self
    {
        $filteredItems = [];

        foreach ($this->data as $key => $item) {
            if ($callback($item, $key)) {
                $filteredItems[] = $item;
            }
        }

        return new static($this->model, $filteredItems);
    }

    /**
     * Execute a callback over each item.
     *
     * @param callable $callback
     * @return $this
     */
    public function each(callable $callback)
    {
        foreach ($this->data as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Add an item to the end of the collection.
     *
     * @param mixed $item
     * @return $this
     */
    public function push(mixed $item): self
    {
        $this->data[] = $item;

        return $this;
    }

    /**
     * Flatten a multi-dimensional collection into a single level.
     *
     * @param int $depth The maximum depth to flatten (default: infinite)
     * @return static
     */
    public function flatten(int $depth = PHP_INT_MAX): self
    {
        $result = [];
        $stack = [];

        foreach (array_reverse($this->data) as $item) {
            $stack[] = ['item' => $item, 'depth' => 0];
        }

        while (!empty($stack)) {
            $current = array_pop($stack);
            $item = $current['item'];
            $currentDepth = $current['depth'];

            if (is_array($item) && $currentDepth < $depth) {
                // Only process array values (ignoring keys)
                foreach (array_reverse(array_values($item)) as $subItem) {
                    $stack[] = [
                        'item' => is_array($subItem) ? $subItem : $subItem,
                        'depth' => $currentDepth + 1
                    ];
                }
            } else {
                $result[] = $item;
            }
        }

        return new static($this->model, $result);
    }

    /**
     * Output or return memory usage stats related to the current collection.
     *
     * @param bool $asString If true, returns human-readable string. Otherwise, returns an array.
     * @return string|array
     */
    public function withMemoryUsage(bool $asString = true): string|array
    {
        $usage = memory_get_usage(true);
        $peak  = memory_get_peak_usage(true);

        $data = [
            'current_usage_bytes' => $usage,
            'peak_usage_bytes'    => $peak,
            'current_usage_mb'    => round($usage / 1024 / 1024, 2) . ' MB',
            'peak_usage_mb'       => round($peak / 1024 / 1024, 2) . ' MB',
        ];

        if ($asString) {
            return sprintf(
                "Memory usage: %s, Peak: %s",
                $data['current_usage_mb'],
                $data['peak_usage_mb']
            );
        }

        return $data;
    }

    public function __get($name)
    {
        return $this->data[$name] ?? null;
    }

    public function __isset($name)
    {
        return isset($this->data[$name]);
    }
}
