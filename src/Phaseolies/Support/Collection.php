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
     * Access collection data properties directly.
     *
     * @param string $name The property name to access
     * @return mixed|null The value if exists, null otherwise
     */
    public function __get($name)
    {
        if ($name === 'map') {
            return new class($this) {
                protected $collection;

                public function __construct($collection)
                {
                    $this->collection = $collection;
                }

                public function __get($property)
                {
                    return $this->collection->map(function ($item) use ($property) {
                        if ($item === null) {
                            return null;
                        }
                        if (is_array($item)) {
                            return $item[$property] ?? null;
                        } elseif (is_object($item)) {
                            return $item->{$property} ?? null;
                        }
                        return null;
                    });
                }
            };
        }

        if ($name === 'each') {
            return new class($this) {
                protected $collection;

                public function __construct($collection)
                {
                    $this->collection = $collection;
                }

                public function __call($method, $parameters)
                {
                    return $this->collection->each(function ($item) use ($method, $parameters) {
                        if ($item !== null && method_exists($item, $method)) {
                            $item->{$method}(...$parameters);
                        }
                    });
                }
            };
        }

        return $this->data[$name] ?? null;
    }

    /**
     * Check if a property exists in the collection data
     *
     * @param string $name The property name to check
     * @return bool True if property exists, false otherwise
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
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
     * 
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
     * Convert the collection to an array
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_map(fn($item) => $item instanceof Model ? $item->toArray() : $item, $this->data);
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
    public function each(callable $callback): self
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

            if ($currentDepth >= $depth) {
                $result[] = $item;
                continue;
            }

            if (is_array($item)) {
                foreach (array_reverse(array_values($item)) as $subItem) {
                    $stack[] = [
                        'item' => $subItem,
                        'depth' => $currentDepth + 1
                    ];
                }
            } elseif ($item instanceof Collection) {
                foreach (array_reverse($item->all()) as $subItem) {
                    $stack[] = [
                        'item' => $subItem,
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
     * Get the values
     *
     * @return self
     */
    public function values(): self
    {
        return new static($this->model, array_values($this->data));
    }

    /**
     * Return a new collection with unique items.
     *
     * @param string|null $key Optional key to use for determining uniqueness
     * @param bool $strict Whether to use strict comparison (===)
     * @return static
     */
    public function unique(?string $key = null, bool $strict = false): self
    {
        $uniqueItems = [];
        $exists = [];

        foreach ($this->data as $item) {
            $value = $key !== null
                ? (is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null))
                : $item;

            $serialized = $strict ? serialize($value) : (is_scalar($value) ? $value : serialize($value));

            if (!isset($exists[$serialized])) {
                $exists[$serialized] = true;
                $uniqueItems[] = $item;
            }
        }

        return new static($this->model, $uniqueItems);
    }

    /**
     * Pluck an array of values from a given key.
     *
     * @param string $value The key to pluck values from
     * @param string|null $key Optional key to use as array keys in the result
     * @return static
     */
    public function pluck(string $value, ?string $key = null): self
    {
        $results = [];

        foreach ($this->data as $item) {
            if (is_array($item)) {
                $itemValue = $item[$value] ?? null;
                $itemKey = $key ? ($item[$key] ?? null) : null;
            } else {
                $itemValue = $item->{$value} ?? null;
                $itemKey = $key ? ($item->{$key} ?? null) : null;
            }

            if ($key === null) {
                $results[] = $itemValue;
            } elseif ($itemKey !== null) {
                $results[$itemKey] = $itemValue;
            }
        }

        return new static($this->model, $results);
    }

    /**
     * Determine if the collection is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    /**
     * Determine if the collection is not empty.
     *
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return !empty($this->data);
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

    /**
     * Map the collection and group the results by the given key.
     *
     * @param callable|string $groupBy Key to group by or callback that returns the group key
     * @param callable|null $mapCallback Callback to transform each item (optional)
     * @return array
     */
    public function mapAsGroup($groupBy, ?callable $mapCallback = null): array
    {
        $results = [];

        $groupResolver = $this->buildKeyResolver($groupBy);

        foreach ($this->data as $key => $item) {
            $groupKey = $groupResolver($item, $key);

            $mappedItem = $mapCallback ? $mapCallback($item, $key) : $item;

            if ($groupKey !== null) {
                $groupKey = (string) $groupKey;
                $results[$groupKey][] = $mappedItem;
            }
        }

        return $results;
    }

    /**
     * Map the collection and use the given key as array keys.
     *
     * @param callable|string $keyBy Key to use as array key or callback that returns the key
     * @param callable|null $mapCallback Callback to transform each item (optional)
     * @return array
     */
    public function mapAsKey($keyBy, ?callable $mapCallback = null): array
    {
        $results = [];

        $keyResolver = $this->buildKeyResolver($keyBy);

        foreach ($this->data as $index => $item) {
            $itemKey = $keyResolver($item, $index);

            $mappedItem = $mapCallback ? $mapCallback($item, $index) : $item;

            if ($itemKey !== null) {
                $itemKey = (string) $itemKey;
                $results[$itemKey] = $mappedItem;
            }
        }

        return $results;
    }

    /**
     * Build a key resolver from various input types.
     *
     * @param callable|string $key
     * @return callable
     */
    protected function buildKeyResolver($key): callable
    {
        if (is_callable($key)) {
            return $key;
        }

        return function ($item) use ($key) {
            if (is_array($item)) {
                return $item[$key] ?? null;
            } elseif (is_object($item)) {
                return $item->{$key} ?? null;
            }

            return null;
        };
    }

    /**
     * Group the collection by the given key with mapping capability.
     *
     * @param callable|string $groupBy
     * @param callable|null $mapCallback
     * @return array
     */
    public function groupBy($groupBy, ?callable $mapCallback = null): array
    {
        return $this->mapAsGroup($groupBy, $mapCallback);
    }

    /**
     * Key the collection by the given key with mapping capability
     *
     * @param callable|string $keyBy
     * @param callable|null $mapCallback
     * @return array
     */
    public function keyBy($keyBy, ?callable $mapCallback = null): array
    {
        return $this->mapAsKey($keyBy, $mapCallback);
    }

    /**
     * Transform each item in the collection into one or more key-value pairs grouped by keys.
     *
     * @param callable $callback
     * @return array
     */
    public function mapToGroups(callable $callback): array
    {
        $results = [];

        foreach ($this->data as $key => $item) {
            $result = $callback($item, $key);

            foreach ($result as $groupKey => $value) {
                $results[$groupKey][] = $value;
            }
        }

        return $results;
    }

    /**
     * Transform the collection into a flat associative array with custom keys.
     *
     * @param callable $callback
     * @return array
     */
    public function mapWithKeys(callable $callback): array
    {
        $results = [];

        foreach ($this->data as $key => $item) {
            $result = $callback($item, $key);

            foreach ($result as $mapKey => $mapValue) {
                $results[$mapKey] = $mapValue;
            }
        }

        return $results;
    }

    /**
     * Convert the collection to JSON.
     *
     * @param int $options JSON encoding options
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
}
