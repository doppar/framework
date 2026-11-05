<?php

namespace Phaseolies\Support;

use Traversable;
use Ramsey\Collection\Collection as RamseyCollection;
use Phaseolies\Database\Entity\Model;
use IteratorAggregate;
use ArrayIterator;
use ArrayAccess;
use JsonSerializable;

/**
 * @phpstan-consistent-constructor
 */
class Collection extends RamseyCollection implements IteratorAggregate, ArrayAccess, JsonSerializable
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
     * Methods that can be used as `$collection->method->property` or `$collection->method->call()`.
     *
     * @var array<int, string>
     */
    protected static array $proxies = [
        'filter', 'reject', 'sum', 'avg', 'min', 'max', 'sortBy', 'sortByDesc',
        'groupBy', 'keyBy', 'unique', 'every', 'some', 'flatMap',
    ];

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
     * @param string $name
     * @return mixed|null
     */
    public function __get($name)
    {
        // `map` and `each` have always taken precedence over a data key of the same name.
        if ($name === 'map' || $name === 'each') {
            return new HigherOrderCollectionProxy($this, $name);
        }

        // The newer ones only apply when no data key has that name.
        if (in_array($name, static::$proxies, true) && !array_key_exists($name, $this->data)) {
            return new HigherOrderCollectionProxy($this, $name);
        }

        return $this->data[$name] ?? null;
    }

    /**
     * Check if a property exists in the collection data
     *
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * Determines if the specified offset exists in the data array.
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->data);
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
        if ($offset === null) {
            $this->data[] = $value;

            return;
        }

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
     * Specify data which should be serialized to JSON
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
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
     * Get the first item, or the first item that passes a test.
     *
     * @param callable|null $callback fn($item, $key): bool
     * @param mixed $default
     * @return mixed
     */
    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        foreach ($this->data as $key => $item) {
            if ($callback === null || $callback($item, $key)) {
                return $item;
            }
        }

        return $default instanceof \Closure ? $default() : $default;
    }

    /**
     * Get the last item, or the last item that passes a test.
     *
     * @param callable|null $callback fn($item, $key): bool
     * @param mixed $default
     * @return mixed
     */
    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        foreach (array_reverse($this->data, true) as $key => $item) {
            if ($callback === null || $callback($item, $key)) {
                return $item;
            }
        }

        return $default instanceof \Closure ? $default() : $default;
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
    public function map(callable $callback): static
    {
        $mappedItems = [];

        foreach ($this->data as $item) {
            $mappedItems[] = $callback($item);
        }

        return new static($this->model, $mappedItems);
    }

    /**
     * Filter the collection using the given callback. Without a callback, every
     * item that is "falsy" (null, false, 0, '', []) is removed.
     *
     * @param callable|null $callback
     * @return static
     */
    public function filter(?callable $callback = null): static
    {
        $filteredItems = [];

        foreach ($this->data as $key => $item) {
            if ($callback === null ? (bool) $item : $callback($item, $key)) {
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
    public function each(callable $callback): static
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
    public function push(mixed $item): static
    {
        $this->data[] = $item;

        return $this;
    }

    /**
     * Flatten a multi-dimensional collection into a single level.
     *
     * @param int $depth
     * @return static
     */
    public function flatten(int $depth = PHP_INT_MAX): static
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
     * @return static
     */
    public function values(): static
    {
        return new static($this->model, array_values($this->data));
    }

    /**
     * Return a new collection with unique items.
     *
     * @param string|null $key
     * @param bool $strict
     * @return static
     */
    public function unique(?string $key = null, bool $strict = false): static
    {
        $uniqueItems = [];
        $exists = [];

        foreach ($this->data as $item) {
            $value = $key !== null ? $this->valueFrom($item, $key) : $item;

            // A float used directly as an array key is truncated to an int, which made
            // 1.5 and 1.2 look alike. Scalars are keyed by their string form instead.
            $serialized = $strict ? serialize($value) : (is_scalar($value) ? (string) $value : serialize($value));

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
     * @param string $value
     * @param string|null $key
     * @return static
     */
    public function pluck(string $value, ?string $key = null): static
    {
        $results = [];

        foreach ($this->data as $item) {
            $itemValue = $this->valueFrom($item, $value);
            $itemKey = $key ? $this->valueFrom($item, $key) : null;

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
     * @param bool $asString
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
     * @param callable|string $groupBy
     * @param callable|null $mapCallback
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
     * @param callable|string $keyBy
     * @param callable|null $mapCallback
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
     * @param mixed $key A callback, or the name of a key (dot notation allowed)
     * @return callable
     */
    protected function buildKeyResolver($key): callable
    {
        // A string is a key, never a callback: `date`, `count` and `time` are also PHP functions.
        if (!is_string($key) && is_callable($key)) {
            return $key;
        }

        return fn($item) => $this->valueFrom($item, $key);
    }

    /**
     * Read a key from an item. Works on arrays, objects (including models) and
     * ArrayAccess values, and understands dot notation for nested values:
     * `valueFrom($row, 'user.address.city')`. A key that really contains a dot
     * is tried as it is first.
     *
     * @param mixed $item
     * @param string|int $key
     * @return mixed Null when the value is not there
     */
    public function valueFrom(mixed $item, string|int $key): mixed
    {
        $found = false;
        $value = $this->readKey($item, $key, $found);

        if ($found || !is_string($key) || !str_contains($key, '.')) {
            return $value;
        }

        $current = $item;

        foreach (explode('.', $key) as $segment) {
            $current = $this->readKey($current, $segment, $found);

            if (!$found) {
                return null;
            }
        }

        return $current;
    }

    /**
     * @param mixed $item
     * @param string|int $key
     * @param bool $found Set to whether the key was there
     * @return mixed
     */
    private function readKey(mixed $item, string|int $key, bool &$found = false): mixed
    {
        $found = false;

        if (is_array($item)) {
            if (array_key_exists($key, $item)) {
                $found = true;

                return $item[$key];
            }

            return null;
        }

        if (is_object($item)) {
            if (isset($item->{$key})) {
                $found = true;

                return $item->{$key};
            }

            if ($item instanceof ArrayAccess && isset($item[$key])) {
                $found = true;

                return $item[$key];
            }
        }

        return null;
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
     * Sort the collection by a given key or callback, or by several of them.
     *
     *     $users->sortBy('age');
     *     $users->sortBy('profile.city');                       // dot notation
     *     $users->sortBy(['age', 'name']);                      // by age, then name
     *     $users->sortBy([['age', 'desc'], ['name', 'asc']]);   // with a direction each
     *
     * @param callable|string|array $callback
     * @param int $options
     * @param bool $descending
     * @return static
     */
    public function sortBy($callback, int $options = SORT_REGULAR, bool $descending = false): static
    {
        if (is_array($callback) && !is_callable($callback)) {
            return $this->sortByMany($callback, $options, $descending);
        }

        $results = [];

        $resolver = $this->buildKeyResolver($callback);

        foreach ($this->data as $key => $item) {
            $results[$key] = $resolver($item, $key);
        }

        if ($descending) {
            arsort($results, $options);
        } else {
            asort($results, $options);
        }

        $sortedData = [];
        foreach (array_keys($results) as $key) {
            $sortedData[] = $this->data[$key];
        }

        return new static($this->model, $sortedData);
    }

    /**
     * Sort by several criteria; each is a key, a callback, or [key, 'asc'|'desc'].
     *
     * @param array $criteria
     * @param int $options
     * @param bool $descending
     * @return static
     */
    protected function sortByMany(array $criteria, int $options, bool $descending): static
    {
        $resolvers = [];

        foreach ($criteria as $criterion) {
            $direction = $descending;

            if (is_array($criterion) && !is_callable($criterion)) {
                [$criterion, $named] = array_pad(array_values($criterion), 2, null);
                $direction = $named === null ? $descending : (is_bool($named) ? $named : strtolower((string) $named) === 'desc');
            }

            $resolvers[] = [$this->buildKeyResolver($criterion), $direction];
        }

        $keyed = [];
        foreach ($this->data as $key => $item) {
            $keyed[] = [$item, array_map(fn($r) => $r[0]($item, $key), $resolvers)];
        }

        // usort is stable, so items that tie on every criterion keep their order.
        usort($keyed, function ($a, $b) use ($resolvers, $options) {
            foreach ($resolvers as $i => [, $desc]) {
                $result = $this->compareValues($a[1][$i], $b[1][$i], $options);

                if ($result !== 0) {
                    return $desc ? -$result : $result;
                }
            }

            return 0;
        });

        return new static($this->model, array_column($keyed, 0));
    }

    /**
     * @param mixed $a
     * @param mixed $b
     * @param int $options One of the SORT_* flags, optionally with SORT_FLAG_CASE
     * @return int
     */
    protected function compareValues(mixed $a, mixed $b, int $options): int
    {
        $flags = $options & ~SORT_FLAG_CASE;
        $caseless = ($options & SORT_FLAG_CASE) === SORT_FLAG_CASE;

        return match ($flags) {
            SORT_STRING => $caseless ? strcasecmp((string) $a, (string) $b) <=> 0 : strcmp((string) $a, (string) $b) <=> 0,
            SORT_NATURAL => $caseless ? strnatcasecmp((string) $a, (string) $b) : strnatcmp((string) $a, (string) $b),
            SORT_NUMERIC => (float) $a <=> (float) $b,
            default => $a <=> $b,
        };
    }

    /**
     * Sort the collection in descending order by a given key or callback.
     *
     * @param callable|string $callback
     * @param int $options
     * @return static
     */
    public function sortByDesc($callback, int $options = SORT_REGULAR): static
    {
        return $this->sortBy($callback, $options, true);
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
     * @param int $options
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Take the first or last {$limit} items from the collection.
     *
     * @param int $limit
     * @return static
     */
    public function take(int $limit): static
    {
        if ($limit < 0) {
            return $this->takeLast(abs($limit));
        }

        return new static($this->model, array_slice($this->data, 0, $limit));
    }

    /**
     * Take the last {$limit} items from the collection.
     *
     * @param int $limit
     * @return static
     */
    public function takeLast(int $limit): static
    {
        if ($limit <= 0) {
            return new static($this->model, []);
        }

        return new static($this->model, array_slice($this->data, -$limit));
    }

    /**
     * Ensure the model is preserved when the collection is serialized by cache.
     *
     * @return array
     */
    public function __serialize(): array
    {
        $parent = [];

        // If the parent defines __serialize, merge its payload to avoid losing state.
        if (method_exists(get_parent_class($this) ?: '', '__serialize')) {
            $parent = parent::__serialize();
        }

        $payload = [
            '__ph_model' => $this->model,
            '__ph_data' => $this->data,
        ];

        // Merge parent state (if any) with our own
        return array_merge($parent, $payload);
    }

    /**
     * Restore the model and data when the collection is unserialized by cache.
     *
     * @param array $data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        // Restore our own state
        $this->model = $data['__ph_model'] ?? $this->model ?? null;
        $this->data = $data['__ph_data'] ?? $this->data ?? [];

        // If the parent defines __unserialize, pass remaining data to it
        if (method_exists(get_parent_class($this) ?: '', '__unserialize')) {
            unset($data['__ph_model'], $data['__ph_data']);
            parent::__unserialize($data);
        }
    }

    /**
     * The type of the items, as the parent collection reports it. Doppar collections
     * hold anything, so this is the model they were created for.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->model ?: 'mixed';
    }

    /**
     * Combine this collection with other collections or arrays into a new one.
     * Numeric keys are renumbered; a string key from a later one replaces an earlier one.
     *
     * @param iterable ...$collections
     * @return static
     */
    public function merge(iterable ...$collections): static
    {
        $merged = [$this->data];

        foreach ($collections as $collection) {
            $merged[] = $collection instanceof self
                ? $collection->all()
                : (is_array($collection) ? $collection : iterator_to_array($collection));
        }

        return new static($this->model, array_merge(...$merged));
    }

    /**
     * Get the model class name associated to this collection.
     *
     * @return ?string
     */
    public function getModel(): ?string
    {
        return $this->model ?? null;
    }

    /**
     * Split collection into chunks of given size.
     *
     * @param int $size
     * @return static
     */
    public function chunk(int $size): static
    {
        if ($size <= 0) {
            throw new \InvalidArgumentException('Chunk size must be greater than 0');
        }

        $chunks = [];
        foreach (array_chunk($this->data, $size, false) as $chunk) {
            $chunks[] = $chunk;
        }

        return new static($this->model, $chunks);
    }

    /**
     * Split collection into two groups based on condition
     *
     * @param callable $callback
     * @return array [passedCollection, failedCollection]
     */
    public function partition(callable $callback): array
    {
        $passed = [];
        $failed = [];

        foreach ($this->data as $key => $item) {
            if ($callback($item, $key)) {
                $passed[] = $item;
            } else {
                $failed[] = $item;
            }
        }

        return [
            new static($this->model, $passed),
            new static($this->model, $failed)
        ];
    }

    /**
     * Get items not present in the given items.
     *
     * @param mixed $items
     * @return static
     */
    public function diff($items): static
    {
        $compare = $items instanceof self ? $items->all() : (array) $items;

        $result = [];
        foreach ($this->data as $item) {
            if (!in_array($item, $compare, true)) {
                $result[] = $item;
            }
        }

        return new static($this->model, $result);
    }

    /**
     * Get items present in both this collection and given items
     *
     * @param mixed $items
     * @return static
     */
    public function intersect($items): static
    {
        $compare = $items instanceof self ? $items->all() : (array) $items;

        $result = [];
        foreach ($this->data as $item) {
            if (in_array($item, $compare, true)) {
                $result[] = $item;
            }
        }

        return new static($this->model, $result);
    }

    /**
     * Execute a callback over the collection without modifying it.
     *
     * @param callable $callback
     * @return $this
     */
    public function tap(callable $callback): static
    {
        $callback($this);

        return $this;
    }

    /**
     * Pass the collection to a callback and return the result
     *
     * @param callable $callback
     * @return mixed
     */
    public function pipe(callable $callback): mixed
    {
        return $callback($this);
    }

    /**
     * Find duplicate items in the collection.
     *
     * @param string|null $key
     * @return static
     */
    public function duplicates(?string $key = null): static
    {
        $seen = [];
        $duplicates = [];

        foreach ($this->data as $item) {
            $value = $key !== null ? $this->valueFrom($item, $key) : $item;

            $serialized = is_scalar($value) ? (string) $value : serialize($value);

            if (isset($seen[$serialized])) {
                $duplicates[] = $item;
            } else {
                $seen[$serialized] = true;
            }
        }

        return new static($this->model, $duplicates);
    }

    /**
     * Get the sum of values
     *
     * @param string|callable|null $callback
     * @return int|float
     */
    public function sum($callback = null): int|float
    {
        if ($callback === null) {
            return array_sum($this->data);
        }

        if (is_string($callback)) {
            return $this->pluck($callback)->sum();
        }

        return $this->map($callback)->sum();
    }

    /**
     * Get the average (mean) value.
     *
     * @param string|callable|null $callback
     * @return int|float|null
     */
    public function avg($callback = null): int|float|null
    {
        $count = $this->count();

        if ($count === 0) {
            return null;
        }

        return $this->sum($callback) / $count;
    }

    /**
     * Get the minimum value.
     *
     * @param string|callable|null $callback
     * @return mixed
     */
    public function min($callback = null): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }

        if ($callback === null) {
            return min($this->data);
        }

        if (is_string($callback)) {
            return $this->pluck($callback)->min();
        }

        return $this->map($callback)->min();
    }

    /**
     * Get the maximum value.
     *
     * @param string|callable|null $callback
     * @return mixed
     */
    public function max($callback = null): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }

        if ($callback === null) {
            return max($this->data);
        }

        if (is_string($callback)) {
            return $this->pluck($callback)->max();
        }

        return $this->map($callback)->max();
    }

    /**
     * Get a single item that matches the criteria, or throw an exception.
     *
     * @param callable|null $callback
     * @return mixed
     * @throws \RuntimeException
     */
    public function sole(?callable $callback = null): mixed
    {
        $filtered = $callback ? $this->filter($callback) : $this;

        $count = $filtered->count();

        if ($count === 0) {
            throw new \RuntimeException('No items found matching the criteria');
        }

        if ($count > 1) {
            throw new \RuntimeException("Multiple items found ({$count} items), expected exactly one");
        }

        return $filtered->first();
    }

    /**
     * Get the items that do not pass the test (the opposite of filter).
     *
     * @param callable $callback fn($item, $key): bool
     * @return static
     */
    public function reject(callable $callback): static
    {
        return $this->filter(fn($item, $key) => !$callback($item, $key));
    }

    /**
     * Determine whether every item passes the test. True for an empty collection.
     *
     * @param callable $callback fn($item, $key): bool
     * @return bool
     */
    public function every(callable $callback): bool
    {
        foreach ($this->data as $key => $item) {
            if (!$callback($item, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether at least one item passes the test. False for an empty collection.
     *
     * @param callable $callback fn($item, $key): bool
     * @return bool
     */
    public function some(callable $callback): bool
    {
        foreach ($this->data as $key => $item) {
            if ($callback($item, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reverse the order of the items.
     *
     * @return static
     */
    public function reverse(): static
    {
        return new static($this->model, array_reverse($this->data));
    }

    /**
     * Get the keys of the collection as a new collection.
     *
     * @return static
     */
    public function keys(): static
    {
        return new static($this->model, array_keys($this->data));
    }

    /**
     * Keep only the items with the given keys.
     *
     * @param array $keys
     * @return static
     */
    public function only(array $keys): static
    {
        return new static($this->model, array_intersect_key($this->data, array_flip($keys)));
    }

    /**
     * Remove the items with the given keys.
     *
     * @param array $keys
     * @return static
     */
    public function except(array $keys): static
    {
        return new static($this->model, array_diff_key($this->data, array_flip($keys)));
    }

    /**
     * Get the first item whose value for a key matches.
     *
     *     $users->firstWhere('active');              // truthy
     *     $users->firstWhere('age', 30);             // equal (==)
     *     $users->firstWhere('age', '>=', 18);       // with an operator
     *
     * @param string $key
     * @param mixed $operator
     * @param mixed $value
     * @return mixed
     */
    public function firstWhere(string $key, mixed $operator = null, mixed $value = null): mixed
    {
        $arguments = func_num_args();

        return $this->first(function ($item) use ($key, $operator, $value, $arguments) {
            $retrieved = $this->valueFrom($item, $key);

            return match ($arguments) {
                1 => (bool) $retrieved,
                2 => $this->compareWith($retrieved, '=', $operator),
                default => $this->compareWith($retrieved, (string) $operator, $value),
            };
        });
    }

    /**
     * @param mixed $retrieved
     * @param string $operator
     * @param mixed $value
     * @return bool
     */
    protected function compareWith(mixed $retrieved, string $operator, mixed $value): bool
    {
        return match ($operator) {
            '=', '==' => $retrieved == $value,
            '===' => $retrieved === $value,
            '!=', '<>' => $retrieved != $value,
            '!==' => $retrieved !== $value,
            '<' => $retrieved < $value,
            '>' => $retrieved > $value,
            '<=' => $retrieved <= $value,
            '>=' => $retrieved >= $value,
            default => throw new \InvalidArgumentException("Unknown comparison operator [{$operator}]."),
        };
    }

    /**
     * Keep the items whose value for a key is one of the given values.
     *
     * @param string $key
     * @param iterable $values
     * @param bool $strict
     * @return static
     */
    public function whereIn(string $key, iterable $values, bool $strict = false): static
    {
        $values = is_array($values) ? $values : iterator_to_array($values);

        return $this->filter(fn($item) => in_array($this->valueFrom($item, $key), $values, $strict));
    }

    /**
     * Keep the items whose value for a key is none of the given values.
     *
     * @param string $key
     * @param iterable $values
     * @param bool $strict
     * @return static
     */
    public function whereNotIn(string $key, iterable $values, bool $strict = false): static
    {
        $values = is_array($values) ? $values : iterator_to_array($values);

        return $this->filter(fn($item) => !in_array($this->valueFrom($item, $key), $values, $strict));
    }

    /**
     * Keep the items that have a value (not null) for a key.
     *
     * @param string $key
     * @return static
     */
    public function whereNotNull(string $key): static
    {
        return $this->filter(fn($item) => $this->valueFrom($item, $key) !== null);
    }

    /**
     * Keep the items that have no value (null) for a key.
     *
     * @param string $key
     * @return static
     */
    public function whereNull(string $key): static
    {
        return $this->filter(fn($item) => $this->valueFrom($item, $key) === null);
    }

    /**
     * Count how many times each value occurs.
     *
     * @param callable|string|null $callback A key or callback that picks the value; the item itself by default
     * @return array<string, int>
     */
    public function countBy(callable|string|null $callback = null): array
    {
        $resolver = $callback === null ? fn($item) => $item : $this->buildKeyResolver($callback);
        $counts = [];

        foreach ($this->data as $key => $item) {
            $value = $resolver($item, $key);
            $value = is_bool($value) ? (int) $value : (is_scalar($value) || $value === null ? (string) $value : serialize($value));
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Join the items into a string, or one key of each item.
     *
     * @param string $glue
     * @param string|null $key
     * @return string
     */
    public function implode(string $glue, ?string $key = null): string
    {
        $values = $key === null ? $this->data : $this->pluck($key)->all();

        return implode($glue, array_map(fn($value) => (string) $value, $values));
    }

    /**
     * Map each item to an array or collection, then join the results into one level.
     *
     * @param callable $callback
     * @return static
     */
    public function flatMap(callable $callback): static
    {
        return $this->map($callback)->collapse();
    }

    /**
     * Join an array of arrays (or collections) into a single level.
     *
     * @return static
     */
    public function collapse(): static
    {
        return $this->flatten(1);
    }

    /**
     * Skip the first items.
     *
     * @param int $count
     * @return static
     */
    public function skip(int $count): static
    {
        return new static($this->model, array_slice($this->data, max(0, $count)));
    }

    /**
     * Get a part of the collection.
     *
     * @param int $offset
     * @param int|null $length
     * @return static
     */
    public function slice(int $offset, ?int $length = null): static
    {
        return new static($this->model, array_slice($this->data, $offset, $length));
    }

    /**
     * Add items at the end. Numeric keys are renumbered.
     *
     * @param iterable $items
     * @return static
     */
    public function concat(iterable $items): static
    {
        return $this->merge(is_array($items) ? array_values($items) : array_values(iterator_to_array($items)));
    }

    /**
     * Find the key of a value, or of the first item that passes a test.
     *
     * @param mixed $value A value, or a callable fn($item, $key): bool
     * @param bool $strict
     * @return int|string|false
     */
    public function search(mixed $value, bool $strict = false): int|string|false
    {
        $isTest = !is_string($value) && is_callable($value);

        foreach ($this->data as $key => $item) {
            if ($isTest ? $value($item, $key) : ($strict ? $item === $value : $item == $value)) {
                return $key;
            }
        }

        return false;
    }

    /**
     * The middle value. Items that are null are ignored.
     *
     * @param string|null $key Take the value from this key of each item
     * @return int|float|null Null when there are no values
     */
    public function median(?string $key = null): int|float|null
    {
        $values = $key === null ? $this->data : $this->pluck($key)->all();
        $values = array_values(array_filter($values, fn($value) => $value !== null));

        if ($values === []) {
            return null;
        }

        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1
            ? $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    /**
     * Run a callback when the value is truthy. A Closure value is called with the collection first.
     *
     * @param mixed $value
     * @param callable $callback fn($collection, $value)
     * @param callable|null $default Runs when the value is falsy
     * @return static|mixed
     */
    public function when(mixed $value, callable $callback, ?callable $default = null): mixed
    {
        $value = $value instanceof \Closure ? $value($this) : $value;

        if ($value) {
            return $callback($this, $value) ?? $this;
        }

        return $default ? ($default($this, $value) ?? $this) : $this;
    }

    /**
     * Run a callback when the value is falsy.
     *
     * @param mixed $value
     * @param callable $callback fn($collection, $value)
     * @param callable|null $default Runs when the value is truthy
     * @return static|mixed
     */
    public function unless(mixed $value, callable $callback, ?callable $default = null): mixed
    {
        $value = $value instanceof \Closure ? $value($this) : $value;

        if (!$value) {
            return $callback($this, $value) ?? $this;
        }

        return $default ? ($default($this, $value) ?? $this) : $this;
    }

    /**
     * Get all items in the collection
     *
     * @return array
     */
    public function get(): array
    {
        return $this->data;
    }
}
