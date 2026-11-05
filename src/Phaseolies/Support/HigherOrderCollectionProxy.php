<?php

namespace Phaseolies\Support;

/**
 * Lets a collection method be written as a property access or a method call on
 * the items, without a closure:
 *
 *     $users->map->name              // same as map(fn($u) => $u->name)
 *     $users->filter->isActive()     // same as filter(fn($u) => $u->isActive())
 *     $users->sum->points            // same as sum(fn($u) => $u->points)
 *     $users->each->save()           // calls save() on every item that has it
 */
class HigherOrderCollectionProxy
{
    /**
     * @param Collection $collection
     * @param string $method The collection method to apply
     */
    public function __construct(protected Collection $collection, protected string $method)
    {
    }

    /**
     * Apply the collection method with a callback that reads a property of each item.
     *
     * @param string $property
     * @return mixed
     */
    public function __get(string $property): mixed
    {
        return $this->collection->{$this->method}(
            fn($item) => $this->collection->valueFrom($item, $property)
        );
    }

    /**
     * Apply the collection method with a callback that calls a method on each item.
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments): mixed
    {
        // each() has always skipped items that do not have the method.
        if ($this->method === 'each') {
            return $this->collection->each(function ($item) use ($name, $arguments) {
                if (is_object($item) && method_exists($item, $name)) {
                    $item->{$name}(...$arguments);
                }
            });
        }

        return $this->collection->{$this->method}(fn($item) => $item->{$name}(...$arguments));
    }
}
