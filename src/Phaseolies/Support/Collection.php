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
    protected $items = [];

    /**
     * @var string
     */
    protected $model;

    /**
     * @param string $model
     * @param array|null $items
     */
    public function __construct(string $model, ?array $items = [])
    {
        $this->model = $model;
        $this->items = $items;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->items[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
    }

    public function __get($name)
    {
        return $this->items[$name] ?? null;
    }

    public function __isset($name)
    {
        return isset($this->items[$name]);
    }

    /**
     * Required for looping data
     * @return Traversable
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Count the number of items in the collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Get all items in the collection
     *
     * @return array
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Get the first item in the collection
     *
     * @return mixed
     */
    public function first(): mixed
    {
        return $this->items[0] ?? null;
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
        foreach ($this->items as $item) {
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
        foreach ($this->items as $item) {
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
        }, $this->items);
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

        foreach ($this->items as $item) {
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

        foreach ($this->items as $key => $item) {
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
        foreach ($this->items as $key => $item) {
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
        $this->items[] = $item;

        return $this;
    }
}
