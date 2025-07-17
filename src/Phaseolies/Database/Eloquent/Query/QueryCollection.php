<?php

namespace Phaseolies\Database\Eloquent\Query;

use Phaseolies\Utilities\Casts\CastToDate;
use Phaseolies\Support\Collection;
use Phaseolies\Database\Eloquent\Model;
use Phaseolies\Database\Eloquent\Builder;
use Phaseolies\Database\Database;

/**
 * The QueryCollection trait provides methods for querying the database
 * and interacting with model collections. It is designed to be used
 * within Eloquent models to enable fluent query building and data retrieval.
 */
trait QueryCollection
{
    /**
     * @var bool
     */
    protected static bool $isHookShouldBeCalled = true;

    /**
     * Creates and returns a new query builder instance for the model.
     *
     * @return \Phaseolies\Database\Eloquent\Builder
     */
    public static function query(): Builder
    {
        $model = new static();

        return new Builder(
            Database::getPdoInstance(),
            $model->table,
            get_class($model),
            $model->pageSize
        );
    }

    /**
     * Disable the execution of model hooks for the current instance.
     *
     * Call this method when you want to perform operations on the model
     * without triggering any defined hooks (e.g., before_deleted, etc.).
     *
     * @return self
     */
    public static function withoutHook(): self
    {
        self::$isHookShouldBeCalled = false;

        return app(static::class);
    }

    /**
     * Retrieves all records from the model's table.
     *
     * @return Collection A collection of all model records.
     */
    public static function all(): Collection
    {
        return static::query()->get();
    }

    /**
     * Alias for the `all` method. Retrieves all records from the model's table.
     *
     * @return Collection A collection of all model records.
     */
    public static function get(): Collection
    {
        return static::all();
    }

    /**
     * Finds a model record by its primary key.
     *
     * @param string|array $primaryKey The value of the primary key (can be array or multiple strings)
     * @return mixed
     */
    public static function find(string|int|array $primaryKey)
    {
        $query = static::query();

        $key = (new static)->primaryKey;

        if (is_array($primaryKey)) {
            $models = $query->whereIn($key, $primaryKey)->get();
            foreach ($models as $model) {
                $model->originalAttributes = $model->attributes;
            }
            return $models;
        }

        $model = $query->where($key, $primaryKey)->first();
        if ($model) {
            $model->originalAttributes = $model->attributes;
        }

        return $model;
    }

    /**
     * Returns the total number of records in the model's table.
     *
     * @return int The count of records.
     */
    public static function count(): int
    {
        return static::query()->count();
    }

    /**
     * Converts the model's attributes to an array.
     *
     * @return array The array representation of the model's visible attributes.
     */
    public function toArray(): array
    {
        $attributes = $this->makeVisible();

        // Include loaded relationships
        foreach ($this->relations as $key => $relation) {
            if ($relation === null) {
                $attributes[$key] = null;
            } elseif ($relation instanceof Model) {
                $attributes[$key] = $relation->toArray();
            } elseif ($relation instanceof Collection) {
                $attributes[$key] = $relation->all();
            } else {
                $attributes[$key] = $relation;
            }
        }

        return $attributes;
    }

    /**
     * Pluck an array of values from a single column.
     *
     * @param string $column
     * @return Collection
     */
    public function pluck(string $column): Collection
    {
        $values = [];
        foreach ($this->get() as $item) {
            $values[] = $item->{$column};
        }
        return new Collection($this->modelClass, $values);
    }

    /**
     * Converts the model's attributes to a JSON string.
     *
     * @param int $options Bitmask of JSON encoding options.
     * @return string The JSON representation of the model.
     * @throws \Exception If JSON encoding fails.
     */
    public function toJson($options = 0): string
    {
        try {
            $json = json_encode($this->jsonSerialize(), $options | JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        return $json;
    }

    /**
     * Save the model to the database.
     *
     * @return bool
     */
    public function save(): bool
    {
        static $attributeCache = [];
        $class = static::class;

        if (!array_key_exists($class, $attributeCache)) {
            $attributeCache[$class] = $this->propertyHasAttribute(new static(), 'timeStamps', CastToDate::class);
        }

        $dateTime = $attributeCache[$class]
            ? now()->startOfDay()
            : now();

        $isUpdatable = isset($this->attributes[$this->primaryKey]);

        if ($isUpdatable) {
            $dirtyAttributes = $this->getDirtyAttributes();
            if (!empty($this->creatable)) {
                $dirtyAttributes = array_intersect_key($dirtyAttributes, array_flip($this->creatable));
            }

            if (empty($dirtyAttributes)) {
                return true;
            }

            if ($this->timeStamps) {
                $dirtyAttributes['updated_at'] = $dateTime;
            }

            $primaryKeyValue = $this->attributes[$this->primaryKey];

            if (self::$isHookShouldBeCalled && $this->fireBeforeHooks('updated') === false) {
                return false;
            }

            $response = $this->query()
                ->where($this->primaryKey, $primaryKeyValue)
                ->update($dirtyAttributes);

            if (self::$isHookShouldBeCalled && $response) {
                $this->fireAfterHooks('updated');
                $this->originalAttributes = $this->attributes;
            }

            return $response;
        }

        $attributes = $this->getCreatableAttributes();

        if ($this->timeStamps) {
            $attributes['created_at'] = $dateTime;
            $attributes['updated_at'] = $dateTime;
        }

        if (self::$isHookShouldBeCalled && $this->fireBeforeHooks('created') === false) {
            return false;
        }

        $id = $this->query()->insert($attributes);

        if ($id && self::$isHookShouldBeCalled) {
            $this->fireAfterHooks('created');
        }

        if ($id) {
            $this->attributes[$this->primaryKey] = $id;
            return true;
        }

        return false;
    }

    /**
     * Get model dirty attributes
     *
     * @return array
     */
    public function getDirtyAttributes(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (
                !array_key_exists($key, $this->originalAttributes) ||
                $this->originalAttributes[$key] != $value
            ) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Insert multiple records into the database
     *
     * @param array $rows Array of attribute sets
     * @return int Number of inserted rows
     */
    public static function saveMany(array $rows, int $chunkSize = 100): int
    {
        $filteredRows = array_map(function ($row) {
            $model = new static();
            $creatable = $model->creatable;

            if (empty($creatable)) {
                $creatable = array_keys($row);
                if (($key = array_search($model->primaryKey, $creatable)) !== false) {
                    unset($creatable[$key]);
                }
            }

            return array_intersect_key($row, array_flip($creatable));
        }, $rows);

        return static::query()->insertMany($filteredRows, $chunkSize);
    }

    /**
     * Update an existing record or create a new one if it doesn't exist.
     *
     * @param array $attributes The attributes to match against (for finding existing record)
     * @param array $values The values to update or insert
     * @return Model The updated or newly created model instance
     */
    public static function updateOrCreate(array $attributes, array $values = []): Model
    {
        $query = static::query();

        foreach ($attributes as $field => $value) {
            $query->where($field, $value);
        }

        $model = $query->first();

        if ($model) {
            $model->fill($values);
            $model->save();
        } else {
            $model = static::create(array_merge($attributes, $values));
        }

        return $model;
    }

    /**
     * Create a new model instance and save it to the database.
     *
     * @param array $attributes
     * @return static
     */
    public static function create(array $attributes): static
    {
        $model = new static();
        $model->fill($attributes);
        $model->save();

        return $model;
    }

    /**
     * Get the attributes that are allowed to be mass-assigned.
     *
     * @return array
     */
    protected function getCreatableAttributes(): array
    {
        $creatableAttributes = [];
        foreach ($this->creatable as $attribute) {
            if (isset($this->attributes[$attribute])) {
                $creatableAttributes[$attribute] = $this->attributes[$attribute];
            }
        }
        return $creatableAttributes;
    }

    /**
     * Filter models based on dynamic conditions
     *
     * @param array|callable $filters Array of field => value pairs or a callback function
     * @return \Phaseolies\Database\Eloquent\Builder
     */
    public static function match(array|callable $filters): Builder
    {
        $query = static::query();

        if (is_callable($filters)) {
            // Handle callback filter
            $filters($query);
        } else {
            // Handle array of filters
            foreach ($filters as $field => $value) {
                if (is_array($value)) {
                    // Handle array conditions (whereIn, etc.)
                    $query->whereIn($field, $value);
                } elseif ($value === null) {
                    // Handle NULL values
                    $query->whereNull($field);
                } elseif ($value instanceof \Closure) {
                    // Handle closure conditions
                    $value($query);
                } else {
                    // Standard equality check
                    $query->where($field, '=', $value);
                }
            }
        }

        return $query;
    }

    /**
     * Accesses a private or protected property of a class using reflection.
     *
     * @param string $class The fully qualified class name.
     * @param string $attribute The property name to retrieve.
     * @return mixed The value of the specified property.
     *
     * @throws \Exception If the property does not exist on the given class.
     */
    protected function getClassProperty(string $class, string $attribute): mixed
    {
        $reflection = new \ReflectionClass($class);

        if ($reflection->hasProperty($attribute)) {
            $property = $reflection->getProperty($attribute);
            $property->setAccessible(true);

            return $property->isStatic()
                ? $property->getValue()
                : $property->getValue(new $this->modelClass);
        }

        throw new \Exception("Property '{$attribute}' does not exist in class '{$class}'.");
    }

    /**
     * Checks whether a class property has a specific attribute.
     *
     * @param object|string $class The fully qualified class name.
     * @param string $attribute The property name to inspect.
     * @param string $attributeClass The attribute class to check for (e.g. CastToDate::class).
     * @return bool True if the attribute exists on the property, false otherwise.
     *
     * @throws \Exception If the property does not exist on the given class.
     */
    protected function propertyHasAttribute(object|string $class, string $attribute, string $attributeClass): bool
    {
        $reflection = new \ReflectionClass($class);

        if (! $reflection->hasProperty($attribute)) {
            throw new \Exception("Property '{$attribute}' does not exist in class '{$class}'.");
        }

        $property = $reflection->getProperty($attribute);
        $attributes = $property->getAttributes($attributeClass);

        return !empty($attributes);
    }
}
