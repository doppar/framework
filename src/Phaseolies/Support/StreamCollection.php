<?php

namespace Phaseolies\Support;

use Traversable;
use IteratorAggregate;
use Iterator;
use Generator;
use ArrayIterator;

class StreamCollection implements IteratorAggregate
{
    /**
     * The source generator or iterator.
     *
     * @var callable|Generator|Iterator
     */
    protected $source;

    /**
     * Create a new lazy collection.
     *
     * @param callable|Generator|Iterator|array $source
     */
    public function __construct($source)
    {
        if (is_array($source)) {
            $source = new ArrayIterator($source);
        }

        if (!is_callable($source) && !$source instanceof Traversable) {
            throw new \InvalidArgumentException(
                'StreamCollection source must be callable, Generator, Iterator, or array.'
            );
        }

        $this->source = $source;
    }

    /**
     * Create a new lazy collection from a generator function.
     *
     * @param callable $source
     * @return static
     */
    public static function make(callable $source): self
    {
        return new static($source);
    }

    /**
     * Get the iterator for the collection.
     *
     * @return Traversable
     */
    public function getIterator(): Traversable
    {
        $source = $this->source;

        if (is_callable($source)) {
            $result = $source();
            if (!$result instanceof Traversable) {
                throw new \InvalidArgumentException('Callable source must return a Traversable object.');
            }
            return $result;
        }

        if ($source instanceof Traversable) {
            return $source;
        }

        throw new \InvalidArgumentException(
            'StreamCollection source must be callable, Generator, Iterator, or array.'
        );
    }

    /**
     * Map over each item.
     *
     * @param callable $callback
     * @return static
     */
    public function map(callable $callback): self
    {
        return new static(function () use ($callback) {
            foreach ($this as $key => $value) {
                yield $key => $callback($value, $key);
            }
        });
    }

    /**
     * Filter the collection using the given callback.
     *
     * @param callable $callback
     * @return static
     */
    public function filter(callable $callback): self
    {
        return new static(function () use ($callback) {
            foreach ($this as $key => $value) {
                if ($callback($value, $key)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * Chunk the collection into chunks of the given size.
     *
     * @param int $size
     * @return static
     */
    public function chunk(int $size): self
    {
        return new static(function () use ($size) {
            $chunk = [];
            $count = 0;

            foreach ($this as $key => $value) {
                $chunk[$key] = $value;
                $count++;

                if ($count >= $size) {
                    yield new Collection('', $chunk);
                    $chunk = [];
                    $count = 0;
                }
            }

            if (!empty($chunk)) {
                yield new Collection('', $chunk);
            }
        });
    }

    /**
     * Execute a callback over each item.
     *
     * @param callable $callback
     * @return void
     */
    public function each(callable $callback): void
    {
        foreach ($this as $key => $value) {
            $callback($value, $key);
        }
    }

    /**
     * Convert the lazy collection to a regular collection.
     *
     * @param string $model
     * @return Collection
     */
    public function collect(string $model = ''): Collection
    {
        $items = [];

        foreach ($this as $key => $value) {
            $items[$key] = $value;
        }

        return new Collection($model, $items);
    }

    /**
     * Get all items as array.
     *
     * @return array
     */
    public function all(): array
    {
        return iterator_to_array($this, true);
    }

    /**
     * Get the first item from the collection.
     *
     * @return mixed
     */
    public function first()
    {
        foreach ($this as $item) {
            return $item;
        }

        return null;
    }

    /**
     * Count the number of items in the collection.
     *
     * @return int
     */
    public function count(): int
    {
        $count = 0;

        foreach ($this as $item) {
            $count++;
        }

        return $count;
    }

    /**
     * Determine if the collection is empty or not.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        foreach ($this as $item) {
            return false;
        }

        return true;
    }

    /**
     * Determine if the collection is not empty.
     *
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Pluck an array of values from a given key.
     *
     * @param string $value
     * @param string|null $key
     * @return static
     */
    public function pluck(string $value, ?string $key = null): self
    {
        return new static(function () use ($value, $key) {
            foreach ($this as $originalKey => $item) {
                $itemValue = is_array($item) ? ($item[$value] ?? null) : ($item->{$value} ?? null);

                if ($key === null) {
                    yield $originalKey => $itemValue;
                } else {
                    $itemKey = is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null);
                    if ($itemKey !== null) {
                        yield $itemKey => $itemValue;
                    }
                }
            }
        });
    }

    /**
     * Flatten the collection.
     *
     * @param int $depth The maximum depth to flatten (default: infinite)
     * @return static
     */
    public function flatten(int $depth = PHP_INT_MAX): self
    {
        return new static(function () use ($depth) {
            $flattenItem = function ($item, int $currentDepth) use (&$flattenItem, $depth) {
                if ($currentDepth >= $depth) {
                    yield $item;
                    return;
                }

                if (is_array($item)) {
                    foreach ($item as $subItem) {
                        yield from $flattenItem($subItem, $currentDepth + 1);
                    }
                } elseif ($item instanceof Collection) {
                    foreach ($item->all() as $subItem) {
                        yield from $flattenItem($subItem, $currentDepth + 1);
                    }
                } elseif ($item instanceof StreamCollection) {
                    foreach ($item as $subItem) {
                        yield from $flattenItem($subItem, $currentDepth + 1);
                    }
                } else {
                    yield $item;
                }
            };

            foreach ($this as $item) {
                yield from $flattenItem($item, 0);
            }
        });
    }

    /**
     * Get unique items from the collection.
     *
     * @param string|null $key
     * @param bool $strict
     * @return static
     */
    public function unique(?string $key = null, bool $strict = false): self
    {
        return new static(function () use ($key, $strict) {
            $items = [];

            foreach ($this as $item) {
                $value = $key !== null
                    ? (is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null))
                    : $item;

                $serialized = $strict
                    ? serialize($value)
                    : (is_scalar($value) ? $value : serialize($value));

                $items[$serialized] = $item;
            }

            // Yield items in insertion order
            foreach ($items as $item) {
                yield $item;
            }
        });
    }

    /**
     * Skip the first $count items.
     *
     * @param int $count
     * @return static
     */
    public function skip(int $count): self
    {
        return new static(function () use ($count) {
            $i = 0;
            foreach ($this as $key => $value) {
                if ($i++ >= $count) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * Take the first $count items.
     *
     * @param int $count
     * @return static
     */
    public function take(int $count): self
    {
        return new static(function () use ($count) {
            $i = 0;
            foreach ($this as $key => $value) {
                if ($i++ < $count) {
                    yield $key => $value;
                } else {
                    break;
                }
            }
        });
    }

    /**
     * Get the values (reset keys)
     *
     * @return static
     */
    public function values(): self
    {
        return new static(function () {
            foreach ($this as $value) {
                yield $value;
            }
        });
    }

    /**
     * Add an item to the end of the collection.
     *
     * @param mixed $item
     * @return $this
     */
    public function push(mixed $item): self
    {
        $currentSource = $this->source;

        $this->source = function () use ($currentSource, $item) {
            foreach (is_callable($currentSource) ? $currentSource() : $currentSource as $key => $value) {
                yield $key => $value;
            }
            yield $item;
        };

        return $this;
    }

    /**
     * Convert the collection to JSON.
     *
     * @param int $options JSON encoding options
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return $this->collect()->toJson($options);
    }

    /**
     * Convert the collection to an array
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->collect()->toArray();
    }
}
